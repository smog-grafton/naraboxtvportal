<?php

namespace App\Services;

use App\Mail\DynamicMail;
use App\Models\CreatorNotificationDelivery;
use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CreatorCommunicationService
{
    public function __construct(private readonly UserNotificationService $notifications) {}

    public function notify(
        User $user,
        string $eventKey,
        string $templateName,
        string $title,
        string $message,
        string $actionUrl,
        array $data = [],
        string $type = 'creator_update'
    ): CreatorNotificationDelivery {
        $existing = CreatorNotificationDelivery::query()->where('event_key', $eventKey)->first();
        if ($existing) {
            return $existing;
        }

        $action = [
            'label' => (string) ($data['action_label'] ?? 'View update'),
            'url' => $actionUrl,
        ];
        $structuredData = array_filter([
            'notification_type' => $templateName,
            'application_id' => isset($data['application_id']) ? (int) $data['application_id'] : null,
            'request_id' => isset($data['request_id']) ? (int) $data['request_id'] : null,
            'claim_id' => isset($data['claim_id']) ? (int) $data['claim_id'] : null,
            'content_id' => isset($data['content_id']) ? (int) $data['content_id'] : null,
            'action' => $action,
        ], fn ($value) => $value !== null);

        $delivery = DB::transaction(function () use ($user, $eventKey, $templateName, $title, $message, $actionUrl, $type, $structuredData): CreatorNotificationDelivery {
            $notification = $this->notifications->createForUser($user->id, [
                'title' => $title,
                'message' => $message,
                'type' => $type,
                'action_url' => $actionUrl,
                'data' => $structuredData,
            ]);

            return CreatorNotificationDelivery::create([
                'user_id' => $user->id,
                'user_notification_id' => $notification->id,
                'template_name' => $templateName,
                'event_key' => $eventKey,
                'email_status' => 'pending',
                'safe_metadata' => $structuredData,
            ]);
        });

        $template = EmailTemplate::getByName($templateName);
        if (! $template || blank($user->email)) {
            $delivery->update([
                'email_status' => $template ? 'skipped_no_address' : 'skipped_missing_template',
                'email_error' => $template ? null : "Template {$templateName} is not active.",
            ]);

            return $delivery->refresh();
        }

        try {
            $safeData = collect($data)
                ->map(fn ($value) => is_scalar($value) ? e((string) $value) : '')
                ->all();
            $absoluteActionUrl = preg_match('/^https?:\/\//i', $actionUrl)
                ? $actionUrl
                : rtrim((string) config('app.frontend_url'), '/').'/'.ltrim($actionUrl, '/');
            Mail::to($user->email)->queue(new DynamicMail($templateName, array_merge([
                'user_name' => e($user->name ?: 'Creator'),
                'message' => e($message),
                'action_url' => $absoluteActionUrl,
            ], $safeData)));
            $delivery->update(['email_status' => 'queued']);
        } catch (\Throwable $exception) {
            Log::warning('Creator lifecycle email could not be queued.', [
                'delivery_id' => $delivery->id,
                'template' => $templateName,
                'exception' => $exception->getMessage(),
            ]);
            $delivery->update([
                'email_status' => 'failed',
                'email_error' => mb_substr($exception->getMessage(), 0, 1000),
            ]);
        }

        return $delivery->refresh();
    }
}
