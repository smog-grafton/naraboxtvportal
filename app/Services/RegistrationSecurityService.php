<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserIpActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class RegistrationSecurityService
{
    public function __construct(
        private readonly ClientIdentityService $client,
        private readonly RuntimeSecuritySettings $settings,
        private readonly SecurityRuleService $rules,
        private readonly SecurityEventService $events,
        private readonly AccountSecurityService $accounts,
        private readonly IdentityNormalizer $normalizer,
    ) {}

    public function registration(Request $request, array $identities, bool $google = false): ?JsonResponse
    {
        if (! config('security.enabled') || ! config('security.registration.enabled')) {
            return null;
        }
        if ($this->settings->get('lockdown_mode') || ! $this->settings->get('registrations_enabled')
            || ($google && ! $this->settings->get('google_new_registrations_enabled'))) {
            return $this->block($request, 'REGISTRATION_DISABLED', 'New account registration is temporarily unavailable.', $identities);
        }

        $this->recordAlertMatches($request, $identities);

        $identity = $this->rules->findIdentity($identities, ['BLOCK_REGISTRATION', 'BLOCK_LOGIN', 'BAN']);
        $rule = $this->rules->findRule($identities, ['BLOCK_REGISTRATION', 'BLOCK_LOGIN', 'BAN']);
        if ($identity || $rule) {
            return $this->block($request, 'REGISTRATION_BLOCKED', 'We could not complete this request.', $identities, $rule?->id, $identity?->id);
        }

        foreach ($this->registrationLimitKeys($request, $identities) as [$key, $max, $seconds]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                return $this->block($request, 'REGISTRATION_RATE_LIMITED', 'Too many attempts. Please try again later.', $identities, null, null, 429, RateLimiter::availableIn($key));
            }
            RateLimiter::hit($key, $seconds);
        }

        return null;
    }

    public function login(Request $request, User $user, array $identities): ?JsonResponse
    {
        $identities = ['USER_ID' => (string) $user->id] + $identities;
        if ($response = $this->accounts->blockingResponse($user)) {
            $this->events->record('LOGIN_BLOCKED', ['user_id' => $user->id, 'risk_level' => 'HIGH', 'reason' => 'Account state blocked login.'], $request);

            return $response;
        }
        $this->recordAlertMatches($request, $identities, $user);
        $identity = $this->rules->findIdentity($identities, ['BLOCK_LOGIN', 'SUSPEND', 'BAN']);
        $rule = $this->rules->findRule($identities, ['BLOCK_LOGIN', 'SUSPEND', 'BAN']);
        if ($identity || $rule) {
            $this->accounts->enforceMatchedAction(
                $user,
                (string) ($identity?->action ?? $rule?->action),
                (string) ($identity?->reason ?? $rule?->reason ?? 'Configured security control matched.'),
                ['ip_address' => $this->client->ip($request), 'device_id' => $this->client->deviceId($request)]
            );

            return $this->block($request, 'LOGIN_BLOCKED', 'This account is temporarily unavailable. Please contact support.', $identities, $rule?->id, $identity?->id);
        }

        $ip = $this->client->ip($request);
        $device = $this->client->deviceId($request);
        $user->forceFill(['last_login_ip' => $ip, 'last_login_at' => now(), 'last_activity_at' => now()])->save();
        UserIpActivity::create([
            'user_id' => $user->id, 'ip_address' => $ip ?: '0.0.0.0', 'activity_type' => 'LOGIN',
            'device_id' => $device, 'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000), 'occurred_at' => now(),
        ]);
        $this->events->record('LOGIN', ['user_id' => $user->id], $request);

        return null;
    }

    public function stampNewUser(User $user, Request $request, string $activity = 'REGISTRATION'): void
    {
        $ip = $this->client->ip($request);
        $device = $this->client->deviceId($request);
        $user->forceFill([
            'registration_ip' => $ip,
            'registration_user_agent' => mb_substr((string) $request->userAgent(), 0, 2000),
            'registration_device_id' => $device,
            'last_login_ip' => $ip,
            'last_login_at' => now(),
        ])->save();
        UserIpActivity::create([
            'user_id' => $user->id, 'ip_address' => $ip ?: '0.0.0.0', 'activity_type' => $activity,
            'device_id' => $device, 'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000), 'occurred_at' => now(),
        ]);
        $this->events->record($activity, ['user_id' => $user->id], $request);
    }

    private function registrationLimitKeys(Request $request, array $identities): array
    {
        $items = [];
        if ($ip = $this->client->ip($request)) {
            $items[] = ['registration:ip:'.hash('sha256', $ip), (int) config('security.registration.ip.max_attempts'), (int) config('security.registration.ip.window_minutes') * 60];
        }
        if ($email = ($identities['EMAIL'] ?? null)) {
            $items[] = ['registration:email:'.hash('sha256', $this->normalizer->forType('EMAIL', $email)), (int) config('security.registration.email.max_attempts'), (int) config('security.registration.email.window_minutes') * 60];
        }
        if ($device = $this->client->deviceId($request)) {
            $items[] = ['registration:device:'.hash('sha256', $device), (int) config('security.registration.device.max_attempts'), (int) config('security.registration.device.window_minutes') * 60];
        }

        return array_filter($items, fn ($item) => $item[1] > 0 && $item[2] > 0);
    }

    private function block(Request $request, string $code, string $message, array $identities, ?int $ruleId = null, ?int $identityId = null, int $status = 403, ?int $retryAfter = null): JsonResponse
    {
        $this->events->record($status === 429 ? 'REGISTRATION_BLOCKED' : $code, [
            'risk_level' => 'HIGH', 'security_rule_id' => $ruleId, 'security_identity_id' => $identityId,
            'reason' => $code, 'metadata' => ['identity_types' => array_keys($identities)],
        ], $request);
        $response = response()->json(['message' => $message, 'code' => $code] + ($retryAfter ? ['retry_after' => $retryAfter] : []), $status);
        if ($retryAfter) {
            $response->headers->set('Retry-After', (string) $retryAfter);
        }

        return $response;
    }

    private function recordAlertMatches(Request $request, array $identities, ?User $user = null): void
    {
        $identity = $this->rules->findIdentity($identities, ['ALERT', 'ALERT_ONLY']);
        $rule = $this->rules->findRule($identities, ['ALERT', 'ALERT_ONLY']);
        if (! $identity && ! $rule) {
            return;
        }

        $this->events->record($identity ? 'SECURITY_IDENTITY_ALERT' : 'SECURITY_RULE_ALERT', [
            'user_id' => $user?->id,
            'risk_level' => $identity?->risk_level ?? $rule?->risk_level ?? 'WATCH',
            'security_identity_id' => $identity?->id,
            'security_rule_id' => $rule?->id,
            'reason' => 'An alert-only security control matched.',
        ], $request);
    }
}
