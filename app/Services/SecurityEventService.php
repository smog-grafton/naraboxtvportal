<?php

namespace App\Services;

use App\Models\SecurityEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class SecurityEventService
{
    private const SENSITIVE_KEYS = [
        'password', 'password_confirmation', 'token', 'access_token', 'refresh_token',
        'authorization', 'api_key', 'secret', 'otp', 'code', 'totp_secret', 'card_number', 'cvv',
    ];

    public function record(string $type, array $attributes = [], ?Request $request = null): ?SecurityEvent
    {
        if (! Schema::hasTable('security_events')) {
            return null;
        }

        if ($request) {
            $identity = app(ClientIdentityService::class);
            $attributes += [
                'user_id' => $request->user()?->id,
                'ip_address' => $identity->ip($request),
                'device_id' => $identity->deviceId($request),
                'route' => $request->route()?->uri() ?? $request->path(),
            ];
        }

        $attributes['event_type'] = strtoupper($type);
        $attributes['risk_level'] = strtoupper((string) ($attributes['risk_level'] ?? 'NORMAL'));
        $attributes['occurred_at'] = $attributes['occurred_at'] ?? now();
        $attributes['metadata'] = $this->sanitize($attributes['metadata'] ?? []);

        return SecurityEvent::create($attributes);
    }

    public function sanitize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        $sanitized = [];
        foreach ($value as $key => $item) {
            if (in_array(mb_strtolower((string) $key), self::SENSITIVE_KEYS, true)) {
                $sanitized[$key] = '[REDACTED]';
                continue;
            }
            $sanitized[$key] = $this->sanitize($item);
        }

        return $sanitized;
    }
}
