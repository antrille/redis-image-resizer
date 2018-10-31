<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ResizeImage;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Redis;

class ImageController extends Controller
{
    /**
     * Store image and send it to a queue for resizing
     *
     * @param Request $request
     * @return Response
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request)
    {
        $data = $this->validate($request, [
            'image' => [
                'bail', // terminate file validation at the first failure
                'required', // file is required
                'image', // file must be an image
                'dimensions:max_width=2000,max_height=2000', // image must be less than 2000px long on each side
                'max:1024', // image file size limit is 10MB
            ],
            'width' => 'required_without:height|integer',
            'height' => 'integer',
        ]);

        try {
            // move saved image to a storage directory
            $filename = uniqid('img_', true) . '.' . $data['image']->guessExtension();
            $data['image']->move(storage_path('images/original/'), $filename);

            // put image into Redis queue for resizing
            $session = uniqid('ss_');

            ResizeImage::dispatch(
                storage_path('images/original/' . $filename),
                $session,
                array_get($data, 'width'),
                array_get($data, 'height')
            );

            // Set image id as unprocessed in Redis
            Redis::set('image:' . $session, "processing");

        } catch (\Exception $e) {
            report($e);

            return response()
                ->json(['status' => false, 'message' => 'Failed to process image.'], 500);
        }

        return response()
            ->json([
                'status' => true,
                'message' => 'Enqueued for processing.',
                'id' => $session,
                'url' => route('images.show', ['id' => $session])
            ]);
    }

    /**
     * Get image by hash id
     *
     * @param  int $id
     * @return Response
     */
    public function show($id)
    {
        $val = Redis::get('image:'.$id);

        switch ($val) {
            case null:
                return response()
                    ->json([
                        'status' => false,
                        'message' => 'Requested image does not exist.',
                    ], 400);
            case 'error':
                return response()
                    ->json([
                        'status' => false,
                        'message' => 'Failed to process image.',
                    ], 500);
            case 'processing':
                return response()
                    ->json([
                        'status' => true,
                        'message' => 'Image is not yet processed.',
                    ]);
            default:
                return response()
                    ->json([
                        'status' => true,
                        'message' => 'Image URL',
                        'url' => url('resized/' . $val),
                    ]);
        }
    }
}
