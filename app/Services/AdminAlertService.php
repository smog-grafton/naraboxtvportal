<?php

namespace App\Services;

use App\Jobs\SendAdminAlertJob;
use App\Models\AdminActivityAlert;
use App\Models\AdminAlertSetting;

class AdminAlertService
{
    public function queue(string $type, string $title, string $message, array $payload = []): AdminActivityAlert
    {
        $alert = AdminActivityAlert::create([
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'payload' => $payload,
            'status' => 'pending',
        ]);

        SendAdminAlertJob::dispatch($alert->id)->onQueue('communications');

        return $alert;
    }

    public function deliver(AdminActivityAlert $alert): void
    {
        $settings = AdminAlertSetting::current();

        app(CommunicationService::class)->deliverTemplatedEmail(
            to: $settings->alert_email,
            templateName: 'admin_activity_alert',
            data: [
                'title' => $alert->title,
                'message' => nl2br(e($alert->message)),
                'created_at' => $alert->created_at ?? now(),
                'status' => strtoupper($alert->status),
            ],
        );

        $alert->update([
            'status' => 'sent',
            'emailed_at' => now(),
        ]);
    }
}
