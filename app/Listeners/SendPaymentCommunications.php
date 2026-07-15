<?php

namespace App\Listeners;

use App\Events\PaymentFailed;
use App\Events\PaymentSucceeded;
use App\Services\AdminAlertService;
use App\Services\CommunicationService;
use App\Services\UserNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendPaymentCommunications implements ShouldQueue
{
    public function handleSuccess(PaymentSucceeded $event): void
    {
        $transaction = $event->transaction->loadMissing('user', 'subscriptionPlan', 'transactionable', 'paymentGateway');
        $user = $transaction->user;

        if (! $user?->email) {
            return;
        }

        $template = match ($transaction->type) {
            'SUBSCRIPTION' => 'subscription_success',
            'RENT' => 'rent_success',
            'BUY' => 'buy_success',
            default => 'payment_success',
        };

        $title = $transaction->transactionable?->title ?? $transaction->subscriptionPlan?->name;
        $watchUrl = rtrim((string) config('app.url'), '/') . '/';

        app(CommunicationService::class)->queueTemplatedEmail(
            to: $user->email,
            templateName: $template,
            data: [
                'user_name' => $user->name,
                'email' => $user->email,
                'movie_title' => $transaction->type !== 'SUBSCRIPTION' ? $title : '',
                'subscription_plan' => $transaction->subscriptionPlan?->name ?? '',
                'amount' => number_format((float) $transaction->amount, 2),
                'status' => $transaction->status,
                'watch_url' => $watchUrl,
                'created_at' => $transaction->updated_at ?? now(),
                'expiry_date' => $user->renewal_date,
            ],
            userId: $user->id,
        );

        app(UserNotificationService::class)->createForUser($user->id, [
            'title' => 'Payment successful',
            'message' => "Your {$transaction->type} payment was successful.",
            'type' => 'payment',
            'action_url' => $watchUrl,
        ]);

        app(AdminAlertService::class)->queue(
            type: 'payment_success',
            title: 'Successful payment',
            message: "{$user->name} completed a {$transaction->type} payment of UGX {$transaction->amount}.",
            payload: ['transaction_id' => $transaction->id]
        );
    }

    public function handleFailure(PaymentFailed $event): void
    {
        $transaction = $event->transaction->loadMissing('user', 'subscriptionPlan', 'transactionable');
        $user = $transaction->user;

        if ($user?->email) {
            app(CommunicationService::class)->queueTemplatedEmail(
                to: $user->email,
                templateName: strtolower($transaction->status) === 'cancelled' ? 'payment_cancelled' : 'payment_failed',
                data: [
                    'user_name' => $user->name,
                    'email' => $user->email,
                    'amount' => number_format((float) $transaction->amount, 2),
                    'status' => $transaction->status,
                    'created_at' => $transaction->updated_at ?? now(),
                ],
                userId: $user->id,
            );
        }

        app(AdminAlertService::class)->queue(
            type: 'payment_failed',
            title: 'Payment failed',
            message: "Payment {$transaction->transaction_ref} failed: {$event->reason}",
            payload: ['transaction_id' => $transaction->id]
        );
    }
}
