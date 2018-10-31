<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Redis;
use Intervention\Image\Facades\Image;

class ResizeImage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 1;

    protected $filename = null;
    protected $sessionId = null;
    protected $width = null;
    protected $height = null;

    /**
     * Create a new job instance.
     *
     * @param $filename
     * @param $width
     * @param $height
     */
    public function __construct($filename, $sessionId, $width, $height)
    {
        $this->filename = $filename;
        $this->sessionId = $sessionId;
        $this->width = $width;
        $this->height = $height;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if (! $this->filename || ! ($this->width || $this->height)) {
            $this->abort();
            return;
        }

        $image = Image::make($this->filename);

        if ($this->width && $this->height) {
            // resize image to the specified size if both width and height are provided
            $image->resize($this->width, $this->height);
        } else {
            // else resize to the given size with the aspect ratio
            $image->resize($this->width, $this->height, function ($constraint) {
                $constraint->aspectRatio();
            });
        }

        // save image to public directory with default quality set to 90 for JPG
        $newFilename = public_path('resized/'.basename($this->filename));
        $image->save($newFilename);

        // update image status in Redis
        Redis::set('image:'.$this->sessionId, basename($this->filename), 'XX');

        // remove temp file
        @unlink($this->filename);

        // create and enqueue image removal job with 1 hour interval
        RemoveImage::dispatch(
            $newFilename,
            $this->sessionId
        )->delay(now()->addHour());
    }

    /**
     * Fail the job from the queue.
     *
     * @param  \Exception  $exception
     * @return void
     */
    public function failed(\Exception $exception) {
        Redis::set('image:'.$this->sessionId, 'error', 'XX');
        @unlink($this->filename);

        report($exception);
    }
}
