<?php

namespace App\Listeners;

use App\Events\CommentCreated;
use App\Events\CommentReplyCreated;
use App\Services\AdminAlertService;
use App\Services\CommunicationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendCommentNotifications implements ShouldQueue
{
    public function handleComment(CommentCreated $event): void
    {
        $comment = $event->comment->loadMissing('media');

        app(AdminAlertService::class)->queue(
            type: 'comment_posted',
            title: 'New movie comment',
            message: "{$comment->user_name} commented on {$comment->media?->title}: {$comment->text}",
            payload: ['comment_id' => $comment->id]
        );
    }

    public function handleReply(CommentReplyCreated $event): void
    {
        $comment = $event->comment->loadMissing('parent.user', 'media');
        $parentUser = $comment->parent?->user;

        if ($parentUser?->email) {
            app(CommunicationService::class)->queueTemplatedEmail(
                to: $parentUser->email,
                templateName: 'comment_reply',
                data: [
                    'user_name' => $parentUser->name,
                    'movie_title' => $comment->media?->title ?? 'your movie',
                    'status' => 'Reply received',
                    'created_at' => $comment->created_at ?? now(),
                ],
                userId: $parentUser->id,
            );
        }

        app(AdminAlertService::class)->queue(
            type: 'comment_reply',
            title: 'Comment reply posted',
            message: "{$comment->user_name} replied on {$comment->media?->title}.",
            payload: ['comment_id' => $comment->id]
        );
    }
}
