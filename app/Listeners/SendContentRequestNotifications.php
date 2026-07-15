<?php

namespace App\Listeners;

use App\Events\ContentRequested;
use App\Services\AdminAlertService;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendContentRequestNotifications implements ShouldQueue
{
    public function handle(ContentRequested $event): void
    {
        $request = $event->contentRequest;

        app(AdminAlertService::class)->queue(
            type: 'content_request',
            title: 'New content request',
            message: "{$request->title} was requested from {$request->requested_from}.",
            payload: ['content_request_id' => $request->id]
        );
    }
}
