<?php

namespace App\Listeners;

use App\Events\UserRegistered;
use App\Services\AdminAlertService;
use App\Services\CommunicationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendWelcomeCommunication implements ShouldQueue
{
    public function handle(UserRegistered $event): void
    {
        $user = $event->user;

        if ($user->email && ! str_ends_with(strtolower($user->email), '@phone-auth.local')) {
            app(CommunicationService::class)->queueTemplatedEmail(
                to: $user->email,
                templateName: 'welcome',
                data: [
                    'user_name' => $user->name,
                    'email' => $user->email,
                    'created_at' => $user->created_at ?? now(),
                    'unsubscribe_url' => app(CommunicationService::class)->unsubscribeUrlFor($user),
                ],
                userId: $user->id,
            );
        }

        app(AdminAlertService::class)->queue(
            type: 'user_registered',
            title: 'New user registration',
            message: "{$user->name} just registered with {$user->email}.",
            payload: ['user_id' => $user->id]
        );
    }
}
