<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Redis;

class RemoveImage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $filename = null;
    protected $sessionId = null;

    /**
     * Create a new job instance.
     *
     * @param $filename
     * @param $sessionId
     */
    public function __construct($filename, $sessionId)
    {
        $this->filename = $filename;
        $this->sessionId = $sessionId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Redis::del('image:'.$this->sessionId);

        @unlink($this->filename);
    }
}
