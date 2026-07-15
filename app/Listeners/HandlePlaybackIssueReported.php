<?php

namespace App\Listeners;

use App\Events\PlaybackIssueReported;
use App\Services\PlaybackHealthService;
use Illuminate\Contracts\Queue\ShouldQueue;

class HandlePlaybackIssueReported implements ShouldQueue
{
    public function handle(PlaybackIssueReported $event): void
    {
        app(PlaybackHealthService::class)->applyFlags($event->report);
    }
}
