<?php

namespace App\Services;

use App\Models\PushDevice;
use App\Models\PushNotification;
use App\Support\PushDeepLink;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PushNotificationService
{
    /**
     * @return array{success: int, failure: int}
     */
    public static function send(PushNotification $notification): array
    {
        $provider = $notification->provider !== 'default'
            ? $notification->provider
            : config('push.default', 'log');

        $devices = static::matchingDevices($notification, $provider)->get();

        if ($devices->isEmpty()) {
            $notification->update([
                'status' => 'sent',
                'sent_at' => now(),
                'success_count' => 0,
                'failure_count' => 0,
                'last_error' => null,
            ]);

            return ['success' => 0, 'failure' => 0];
        }

        try {
            $result = match ($provider) {
                'onesignal' => static::sendViaOneSignal($notification, $devices),
                'log' => static::logOnly($notification, $devices),
                default => throw new RuntimeException("Unsupported push provider [{$provider}]"),
            };
        } catch (\Throwable $exception) {
            Log::error('PushNotificationService send failed', [
                'notification_id' => $notification->id,
                'provider' => $provider,
                'message' => $exception->getMessage(),
            ]);

            $notification->update([
                'status' => 'failed',
                'sent_at' => now(),
                'success_count' => 0,
                'failure_count' => $devices->count(),
                'last_error' => ['message' => $exception->getMessage()],
            ]);

            return [
                'success' => 0,
                'failure' => $devices->count(),
            ];
        }

        $notification->update([
            'status' => $result['failure'] === 0 ? 'sent' : 'failed',
            'sent_at' => now(),
            'success_count' => $result['success'],
            'failure_count' => $result['failure'],
            'last_error' => $result['failure'] > 0 ? ['message' => 'One or more sends failed'] : null,
        ]);

        return $result;
    }

    protected static function matchingDevices(PushNotification $notification, string $provider): Builder
    {
        $query = PushDevice::query()
            ->where('is_active', true)
            ->where('notifications_enabled', true);

        if ($provider !== 'log') {
            $query->where('provider', $provider);
        }

        if ($notification->notification_type === 'marketing') {
            $query->where('marketing_opt_in', true);
        }

        if ($notification->target_platform !== 'all') {
            $query->where('platform', $notification->target_platform);
        }

        match ($notification->target_audience) {
            'subscribed' => $query->whereHas('user', function (Builder $userQuery) {
                $userQuery->where(function (Builder $subscriptionQuery) {
                    $subscriptionQuery
                        ->whereNotNull('plan')
                        ->orWhereRaw("LOWER(COALESCE(plan_status, '')) = ?", ['active']);
                });
            }),
            'free' => $query->where(function (Builder $freeQuery) {
                $freeQuery
                    ->whereNull('user_id')
                    ->orWhereHas('user', function (Builder $userQuery) {
                        $userQuery->where(function (Builder $subscriptionQuery) {
                            $subscriptionQuery
                                ->whereNull('plan')
                                ->whereRaw("LOWER(COALESCE(plan_status, '')) <> ?", ['active']);
                        });
                    });
            }),
            'custom' => static::applyCustomFilters($query, $notification->filters ?? []),
            default => null,
        };

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected static function applyCustomFilters(Builder $query, array $filters): void
    {
        if (!empty($filters['user_ids']) && is_array($filters['user_ids'])) {
            $query->whereIn('user_id', array_filter($filters['user_ids']));
        }

        if (!empty($filters['platforms']) && is_array($filters['platforms'])) {
            $query->whereIn('platform', array_filter($filters['platforms']));
        }

        if (!empty($filters['roles']) && is_array($filters['roles'])) {
            $roles = array_filter($filters['roles']);
            $query->whereHas('user.role', fn (Builder $roleQuery) => $roleQuery->whereIn('name', $roles));
        }

        if (!empty($filters['device_ids']) && is_array($filters['device_ids'])) {
            $query->whereIn('device_id', array_filter($filters['device_ids']));
        }
    }

    /**
     * @return array{success: int, failure: int}
     */
    protected static function logOnly(PushNotification $notification, Collection $devices): array
    {
        foreach ($devices as $device) {
            Log::info('Push notification (log provider)', [
                'device_id' => $device->id,
                'platform' => $device->platform,
                'token' => $device->token,
                'notification_id' => $notification->id,
                'title' => $notification->title,
                'body' => $notification->body,
                'deep_link' => $notification->deep_link,
                'notification_type' => $notification->notification_type,
            ]);
        }

        return [
            'success' => $devices->count(),
            'failure' => 0,
        ];
    }

    /**
     * @return array{success: int, failure: int}
     */
    protected static function sendViaOneSignal(PushNotification $notification, Collection $devices): array
    {
        $config = config('push.providers.onesignal', []);
        $appId = $config['app_id'] ?? null;
        $apiKey = $config['api_key'] ?? null;
        $baseUrl = rtrim((string) ($config['api_base_url'] ?? 'https://api.onesignal.com'), '/');

        if (!$appId || !$apiKey) {
            throw new RuntimeException('OneSignal credentials are not configured.');
        }

        $payloadData = PushDeepLink::payloadData($notification->deep_link);
        $success = 0;
        $failure = 0;

        foreach ($devices->pluck('token')->filter()->chunk(2000) as $subscriptionIds) {
            $tokens = $subscriptionIds->values()->all();
            $response = Http::withHeaders([
                'Authorization' => 'Key ' . $apiKey,
                'Accept' => 'application/json',
            ])->post($baseUrl . '/notifications?c=push', [
                'app_id' => $appId,
                'target_channel' => 'push',
                'include_subscription_ids' => $tokens,
                'headings' => ['en' => $notification->title],
                'contents' => ['en' => $notification->body],
                'data' => $payloadData,
                'url' => $notification->deep_link,
                'priority' => 10,
                'ios_badgeType' => 'Increase',
                'ios_badgeCount' => 1,
                'big_picture' => $notification->image_url,
                'large_icon' => $notification->image_url,
            ]);

            if ($response->successful()) {
                $success += count($tokens);
                continue;
            }

            $failure += count($tokens);
            Log::error('OneSignal push request failed', [
                'notification_id' => $notification->id,
                'status' => $response->status(),
                'body' => $response->json() ?: $response->body(),
            ]);
        }

        return [
            'success' => $success,
            'failure' => $failure,
        ];
    }
}
