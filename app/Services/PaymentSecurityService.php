<?php

namespace App\Services;

use App\Models\Movie;
use App\Models\PaymentAttempt;
use App\Models\PaymentGateway;
use App\Models\PaymentTransaction;
use App\Models\ProtectedPayer;
use App\Models\TVShow;
use App\Models\User;
use App\Models\UserIpActivity;
use App\Models\UserPurchase;
use App\Models\UserRental;
use App\Models\UserSubscription;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

class PaymentSecurityService
{
    public function __construct(
        private readonly ClientIdentityService $client,
        private readonly IdentityNormalizer $normalizer,
        private readonly RuntimeSecuritySettings $settings,
        private readonly SecurityRuleService $rules,
        private readonly SecurityEventService $events,
        private readonly AccountSecurityService $accounts,
    ) {}

    /** @return array{response: ?JsonResponse, lock: ?Lock} */
    public function begin(Request $request): array
    {
        /** @var User|null $user */
        $user = $request->user();
        if (! $user) {
            return ['response' => response()->json(['message' => 'Authentication is required.'], 401), 'lock' => null];
        }

        $context = $this->context($request, $user);
        foreach ($context as $key => $value) {
            $request->attributes->set('security_'.$key, $value);
        }

        if ($response = $this->accounts->blockingResponse($user, true)) {
            return ['response' => $this->reject($request, $context, 'ACCOUNT_PAYMENT_BLOCKED', 'HIGH', $response), 'lock' => null];
        }

        if ($this->settings->get('lockdown_mode') || ! $this->settings->get('payments_enabled')) {
            return ['response' => $this->rejectWithMessage($request, $context, 'PAYMENTS_DISABLED', 'Payment initiation is temporarily unavailable.', 403, 'HIGH'), 'lock' => null];
        }
        if ($context['gateway'] === 'iotec' && ! $this->settings->get('iotec_enabled')) {
            return ['response' => $this->rejectWithMessage($request, $context, 'IOTEC_DISABLED', 'This payment method is temporarily unavailable.', 403, 'HIGH'), 'lock' => null];
        }
        if ($context['is_mobile_money'] && ! $this->settings->get('mobile_money_enabled')) {
            return ['response' => $this->rejectWithMessage($request, $context, 'MOBILE_MONEY_DISABLED', 'Mobile Money is temporarily unavailable.', 403, 'HIGH'), 'lock' => null];
        }
        if ($context['is_card'] && ! $this->settings->get('card_payments_enabled')) {
            return ['response' => $this->rejectWithMessage($request, $context, 'CARD_PAYMENTS_DISABLED', 'Card payments are temporarily unavailable.', 403, 'HIGH'), 'lock' => null];
        }

        $alertIdentity = $this->rules->findIdentity($context['identities'], ['ALERT', 'ALERT_ONLY']);
        $alertRule = $this->rules->findRule($context['identities'], ['ALERT', 'ALERT_ONLY']);
        if ($alertIdentity || $alertRule) {
            $this->events->record($alertIdentity ? 'SECURITY_IDENTITY_ALERT' : 'SECURITY_RULE_ALERT', [
                'user_id' => $user->id,
                'risk_level' => $alertIdentity?->risk_level ?? $alertRule?->risk_level ?? 'WATCH',
                'security_identity_id' => $alertIdentity?->id,
                'security_rule_id' => $alertRule?->id,
                'payer_phone' => $context['payer_phone'],
                'reason' => 'An alert-only security control matched.',
            ], $request);
        }

        $identity = $this->rules->findIdentity($context['identities'], ['BLOCK_PAYMENT', 'PAYMENT_RESTRICT', 'SUSPEND', 'BAN']);
        $rule = $this->rules->findRule($context['identities'], ['BLOCK_PAYMENT', 'PAYMENT_RESTRICT', 'SUSPEND', 'BAN']);
        if ($identity || $rule) {
            $this->accounts->enforceMatchedAction(
                $user,
                (string) ($identity?->action ?? $rule?->action),
                (string) ($identity?->reason ?? $rule?->reason ?? 'Security rule match.'),
                $context
            );
            $this->events->record($identity ? 'SECURITY_IDENTITY_MATCH' : 'SECURITY_RULE_MATCH', [
                'user_id' => $user->id, 'risk_level' => $identity?->risk_level ?? $rule?->risk_level ?? 'HIGH',
                'security_identity_id' => $identity?->id, 'security_rule_id' => $rule?->id,
                'payer_phone' => $context['payer_phone'], 'reason' => 'Payment blocked by a configured security control.',
            ], $request);

            return ['response' => $this->rejectWithMessage($request, $context, 'PAYMENT_SECURITY_BLOCK', 'Payment initiation is temporarily unavailable for this account.', 403, 'CRITICAL'), 'lock' => null];
        }

        if ($this->alreadyEntitled($request, $user)) {
            $this->events->record('ACTIVE_ENTITLEMENT_BLOCK', ['user_id' => $user->id, 'risk_level' => 'NORMAL'], $request);

            return ['response' => $this->rejectWithMessage($request, $context, 'ACTIVE_ENTITLEMENT', $request->input('type') === 'SUBSCRIPTION' ? 'You already have an active subscription.' : 'You already have access to this title.', 422, 'NORMAL'), 'lock' => null];
        }

        if ($context['payer_phone']) {
            $protected = ProtectedPayer::effective()->where('normalized_phone', $context['payer_phone'])->first();
            if ($protected) {
                $protected->increment('attempt_count');
                $protected->forceFill(['last_attempt_at' => now()])->save();
                $this->events->record('PROTECTED_PAYER_ATTEMPT', [
                    'user_id' => $user->id, 'risk_level' => 'CRITICAL', 'payer_phone' => $context['payer_phone'],
                    'reason' => 'A protected payer number was submitted.',
                ], $request);

                return ['response' => $this->rejectWithMessage($request, $context, 'PAYER_PROTECTED', 'We could not complete this request.', 403, 'CRITICAL'), 'lock' => null];
            }
        }

        $lock = Cache::store(config('security.cache_store'))->lock($this->lockKey($request, $context), (int) config('payment-security.lock_seconds', 30));
        if (! $lock->get()) {
            return ['response' => $this->rejectWithMessage($request, $context, 'PAYMENT_IN_PROGRESS', 'A payment request is already being processed. Please wait.', 429, 'WATCH', 5), 'lock' => null];
        }

        if ($this->newAccountLimitReached($user)) {
            $this->accounts->restrictPayments($user, 'New account exceeded the safe payment-attempt threshold.', null, 'AUTOMATIC', null, $context);

            return ['response' => $this->rejectWithMessage($request, $context, 'NEW_ACCOUNT_PAYMENT_LIMIT', 'Payment initiation is temporarily unavailable for this account.', 403, 'HIGH'), 'lock' => $lock];
        }

        if ($existing = $this->existingIdempotentTransaction($request, $user)) {
            return ['response' => $this->rejectWithMessage($request, $context, 'DUPLICATE_PAYMENT_REQUEST', 'A matching payment request is already being processed.', 429, 'WATCH', 10, ['transaction_ref' => $existing->transaction_ref]), 'lock' => $lock];
        }

        if ($this->pendingLimitReached($request, $user)) {
            return ['response' => $this->rejectWithMessage($request, $context, 'PENDING_PAYMENT_EXISTS', 'A payment request is already pending. Please wait before trying again.', 429, 'WATCH', 30), 'lock' => $lock];
        }

        foreach ($this->limitKeys($context) as [$key, $max, $seconds]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                $retry = max(1, RateLimiter::availableIn($key));
                $this->events->record('PAYMENT_RATE_LIMITED', [
                    'user_id' => $user->id, 'risk_level' => 'HIGH', 'payer_phone' => $context['payer_phone'],
                    'reason' => 'A payment rate limit was exceeded.', 'metadata' => ['dimension' => explode(':', $key)[1] ?? 'unknown', 'retry_after' => $retry],
                ], $request);

                return ['response' => $this->rejectWithMessage($request, $context, 'PAYMENT_RATE_LIMITED', 'Too many payment attempts. Please try again later.', 429, 'HIGH', $retry), 'lock' => $lock];
            }
        }
        foreach ($this->limitKeys($context) as [$key, , $seconds]) {
            RateLimiter::hit($key, $seconds);
        }

        $cooldownKey = $this->cooldownKey($context);
        $cooldown = (int) config('payment-security.minimum_seconds_between_attempts', 20);
        if ($cooldown > 0 && ! Cache::store(config('security.cache_store'))->add($cooldownKey, now()->timestamp, $cooldown)) {
            $this->events->record('PAYMENT_COOLDOWN', ['user_id' => $user->id, 'risk_level' => 'WATCH'], $request);

            return ['response' => $this->rejectWithMessage($request, $context, 'PAYMENT_COOLDOWN', 'Please wait before starting another payment.', 429, 'WATCH', $cooldown), 'lock' => $lock];
        }

        $attempt = $this->recordAttempt($context, false, 'EVALUATING', 'NORMAL');

        if ($this->uniquePayerLimitExceeded($context)) {
            $this->events->record('UNIQUE_PAYER_THRESHOLD', [
                'user_id' => $user->id, 'risk_level' => 'HIGH', 'payer_phone' => $context['payer_phone'],
                'reason' => 'Too many unique payer numbers were submitted by a correlated actor.',
            ], $request);
            if ($this->settings->get('automatic_payment_restriction_enabled')) {
                $this->accounts->restrictPayments($user, 'Excessive payer-number rotation detected.', null, 'AUTOMATIC', null, $context);
            }
            $attempt->update(['decision_code' => 'UNIQUE_PAYER_THRESHOLD', 'risk_level' => 'HIGH', 'risk_reasons' => ['unique_payer_rotation']]);

            return ['response' => $this->json('Payment initiation is temporarily unavailable for this account.', 'PAYMENT_SECURITY_BLOCK', 403), 'lock' => $lock];
        }

        if ($this->enumerationDetected($context)) {
            $this->events->record('PHONE_ENUMERATION_DETECTED', [
                'user_id' => $user->id, 'risk_level' => 'CRITICAL', 'payer_phone' => $context['payer_phone'],
                'reason' => 'Sequential payer-number enumeration was detected.',
            ], $request);
            if (config('payment-security.enumeration.auto_restrict') && $this->settings->get('automatic_payment_restriction_enabled')) {
                $this->accounts->restrictPayments($user, 'Sequential payer-number enumeration detected.', null, 'AUTOMATIC', null, $context);
            }
            $attempt->update(['decision_code' => 'PHONE_ENUMERATION', 'risk_level' => 'CRITICAL', 'risk_reasons' => ['sequential_payer_enumeration']]);

            return ['response' => $this->json('Payment initiation is temporarily unavailable for this account.', 'PAYMENT_SECURITY_BLOCK', 403), 'lock' => $lock];
        }

        $attempt->update(['allowed' => true, 'decision_code' => 'ALLOWED']);
        $user->forceFill(['last_payment_ip' => $context['ip_address'], 'last_activity_at' => now()])->save();
        UserIpActivity::create([
            'user_id' => $user->id,
            'ip_address' => $context['ip_address'] ?: '0.0.0.0',
            'activity_type' => 'PAYMENT_INITIATION',
            'device_id' => $context['device_id'],
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            'metadata' => ['gateway' => $context['gateway'], 'payment_type' => $context['payment_type']],
            'occurred_at' => now(),
        ]);
        $this->events->record('PAYMENT_ATTEMPT', [
            'user_id' => $user->id, 'payer_phone' => $context['payer_phone'], 'provider' => $context['provider'],
            'provider_user_id' => $context['provider_user_id'], 'risk_level' => 'NORMAL',
        ], $request);

        return ['response' => null, 'lock' => $lock];
    }

    private function context(Request $request, User $user): array
    {
        $social = $user->socialAccounts()->latest('last_login_at')->latest('id')->first();
        $route = (string) ($request->route()?->uri() ?? $request->path());
        $method = strtolower((string) $request->input('method', ''));
        $gateway = str_contains($route, 'iotec') ? 'iotec'
            : (str_contains($route, 'pawapay') ? 'pawapay'
                : (str_contains($route, 'flutterwave') ? 'flutterwave'
                    : (string) (PaymentGateway::find($request->input('gateway_id'))?->slug ?? 'generic')));
        $payer = $request->filled('phone') ? $this->normalizer->phone((string) $request->input('phone')) : null;
        $ip = $this->client->ip($request);
        $device = $this->client->deviceId($request);

        $identities = array_filter([
            'USER_ID' => (string) $user->id,
            'DISPLAY_NAME' => $user->name,
            'EMAIL' => $user->email,
            'PHONE' => $user->phone,
            'IP' => $ip,
            'DEVICE_ID' => $device,
            'PAYER_PHONE' => $payer,
        ], fn ($value) => $value !== null && $value !== '');
        if ($social?->provider_user_id) {
            $providerType = match (strtolower((string) $social->provider)) {
                'google' => 'GOOGLE_SUB',
                'apple' => 'APPLE_SUB',
                default => 'SOCIAL_PROVIDER_ID',
            };
            $identities[$providerType] = $social->provider_user_id;
            $identities['SOCIAL_PROVIDER_ID'] = $social->provider_user_id;
        }

        return [
            'user_id' => $user->id,
            'provider' => $social?->provider,
            'provider_user_id' => $social?->provider_user_id,
            'ip_address' => $ip,
            'device_id' => $device,
            'payer_phone' => $payer ?: null,
            'gateway' => $gateway,
            'payment_type' => strtoupper((string) $request->input('type')),
            'is_card' => $method === 'card' || $gateway === 'flutterwave',
            'is_mobile_money' => ($payer !== null && $payer !== '') && $method !== 'card',
            'identities' => $identities,
        ];
    }

    private function alreadyEntitled(Request $request, User $user): bool
    {
        $type = strtoupper((string) $request->input('type'));
        if ($type === 'SUBSCRIPTION') {
            if (UserSubscription::where('user_id', $user->id)->where('status', 'ACTIVE')->where('expires_at', '>', now())->exists()) {
                return true;
            }
            if ($user->plan_status === 'ACTIVE' && strtoupper((string) $user->plan) !== 'FREE'
                && (! $user->renewal_date || $user->renewal_date->isFuture())) {
                return true;
            }

            return $user->subscriptions()->where('status', 'ACTIVE')
                ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', today()))->exists();
        }

        $model = strtoupper((string) $request->input('media_type')) === 'TV_SHOW' ? TVShow::class : Movie::class;
        $id = (int) $request->input('media_id');
        if (! $id) {
            return false;
        }
        if ($type === 'BUY') {
            return UserPurchase::where('user_id', $user->id)->where('purchasable_type', $model)->where('purchasable_id', $id)->exists();
        }
        if ($type === 'RENT') {
            return UserRental::where('user_id', $user->id)->where('rentable_type', $model)->where('rentable_id', $id)
                ->where('is_active', true)->where('expires_at', '>', now())->exists();
        }

        return false;
    }

    private function pendingLimitReached(Request $request, User $user): bool
    {
        $max = (int) config('payment-security.max_pending_per_user', 1);
        if ($max <= 0) {
            return false;
        }

        return PaymentTransaction::where('user_id', $user->id)->where('status', 'PENDING')
            ->where('created_at', '>=', now()->subMinutes((int) config('payment-security.pending_expiry_minutes', 15)))
            ->count() >= $max;
    }

    private function existingIdempotentTransaction(Request $request, User $user): ?PaymentTransaction
    {
        $key = trim((string) $request->header('Idempotency-Key'));
        if ($key === '') {
            return null;
        }

        return PaymentTransaction::where('user_id', $user->id)->where('idempotency_key', mb_substr($key, 0, 128))->first();
    }

    private function newAccountLimitReached(User $user): bool
    {
        if (! config('payment-security.new_account.enabled') || ! $user->created_at?->gt(now()->subMinutes((int) config('payment-security.new_account.window_minutes', 30)))) {
            return false;
        }

        return PaymentAttempt::where('user_id', $user->id)
            ->where('occurred_at', '>=', now()->subMinutes((int) config('payment-security.new_account.window_minutes', 30)))
            ->count() >= (int) config('payment-security.new_account.max_attempts', 3);
    }

    private function limitKeys(array $context): array
    {
        $dimensions = [
            ['user', (string) $context['user_id'], 'user'],
            ['provider', ($context['provider'] && $context['provider_user_id']) ? $context['provider'].':'.$context['provider_user_id'] : null, 'provider'],
            ['ip', $context['ip_address'], 'ip'],
            ['device', $context['device_id'], 'device'],
            ['payer', $context['payer_phone'], 'payer'],
        ];
        $keys = [];
        foreach ($dimensions as [$label, $value, $config]) {
            $max = (int) config("payment-security.{$config}.max_attempts");
            $seconds = (int) config("payment-security.{$config}.window_minutes") * 60;
            if ($value && $max > 0 && $seconds > 0) {
                $keys[] = ['payment:'.$label.':'.hash('sha256', (string) $value), $max, $seconds];
            }
        }

        return $keys;
    }

    private function uniquePayerLimitExceeded(array $context): bool
    {
        if (! $context['payer_phone']) {
            return false;
        }
        $since = now()->subMinutes((int) config('payment-security.unique_payers.window_minutes', 1440));
        $checks = [
            ['user_id', $context['user_id'], (int) config('payment-security.unique_payers.max_per_user', 3)],
            ['provider_user_id', $context['provider_user_id'], (int) config('payment-security.unique_payers.max_per_provider', 3)],
            ['ip_address', $context['ip_address'], (int) config('payment-security.unique_payers.max_per_ip', 5)],
            ['device_id', $context['device_id'], (int) config('payment-security.unique_payers.max_per_device', 3)],
        ];
        foreach ($checks as [$column, $value, $max]) {
            if ($value && $max > 0 && PaymentAttempt::where($column, $value)->where('occurred_at', '>=', $since)
                ->whereNotNull('payer_phone')->distinct('payer_phone')->count('payer_phone') > $max) {
                return true;
            }
        }

        return false;
    }

    private function enumerationDetected(array $context): bool
    {
        if (! config('payment-security.enumeration.enabled') || ! $this->settings->get('enumeration_detection_enabled') || ! $context['payer_phone']) {
            return false;
        }
        $since = now()->subMinutes((int) config('payment-security.enumeration.window_minutes', 30));
        foreach ([
            ['user_id', $context['user_id']],
            ['provider_user_id', $context['provider_user_id']],
            ['ip_address', $context['ip_address']],
            ['device_id', $context['device_id']],
        ] as [$column, $value]) {
            if (! $value) {
                continue;
            }
            $numbers = PaymentAttempt::where($column, $value)->where('occurred_at', '>=', $since)
                ->whereNotNull('payer_phone')->distinct()->pluck('payer_phone')->all();
            if ($this->looksSequential($numbers)) {
                return true;
            }
        }

        return false;
    }

    public function looksSequential(array $numbers): bool
    {
        $minimum = (int) config('payment-security.enumeration.minimum_unique_numbers', 5);
        $prefixLength = (int) config('payment-security.enumeration.prefix_length', 9);
        $maximumSpread = (int) config('payment-security.enumeration.maximum_numeric_spread', 100);
        $groups = [];
        foreach (array_unique(array_filter($numbers, fn ($number) => ctype_digit((string) $number))) as $number) {
            $groups[substr((string) $number, 0, $prefixLength)][] = (int) $number;
        }
        foreach ($groups as $values) {
            if (count($values) >= $minimum && max($values) - min($values) <= $maximumSpread) {
                return true;
            }
        }

        return false;
    }

    private function contextMetadata(array $context): array
    {
        return ['gateway' => $context['gateway'], 'payment_type' => $context['payment_type']];
    }

    private function recordAttempt(array $context, bool $allowed, string $code, string $risk): PaymentAttempt
    {
        return PaymentAttempt::create([
            'user_id' => $context['user_id'], 'provider' => $context['provider'], 'provider_user_id' => $context['provider_user_id'],
            'ip_address' => $context['ip_address'], 'device_id' => $context['device_id'], 'payer_phone' => $context['payer_phone'],
            'gateway' => $context['gateway'], 'payment_type' => $context['payment_type'], 'allowed' => $allowed,
            'decision_code' => $code, 'risk_level' => $risk, 'metadata' => $this->contextMetadata($context), 'occurred_at' => now(),
        ]);
    }

    private function reject(Request $request, array $context, string $code, string $risk, JsonResponse $response): JsonResponse
    {
        $this->recordAttempt($context, false, $code, $risk);
        $this->events->record('PAYMENT_BLOCKED', [
            'user_id' => $context['user_id'], 'risk_level' => $risk, 'payer_phone' => $context['payer_phone'], 'reason' => $code,
        ], $request);

        return $response;
    }

    private function rejectWithMessage(Request $request, array $context, string $code, string $message, int $status, string $risk, ?int $retryAfter = null, array $extra = []): JsonResponse
    {
        return $this->reject($request, $context, $code, $risk, $this->json($message, $code, $status, $retryAfter, $extra));
    }

    private function json(string $message, string $code, int $status, ?int $retryAfter = null, array $extra = []): JsonResponse
    {
        $response = response()->json(array_merge(['message' => $message, 'error' => $message, 'code' => $code], $retryAfter ? ['retry_after' => $retryAfter] : [], $extra), $status);
        if ($retryAfter) {
            $response->headers->set('Retry-After', (string) $retryAfter);
        }

        return $response;
    }

    private function cooldownKey(array $context): string
    {
        $actor = $context['provider_user_id'] ?: $context['device_id'] ?: 'user:'.$context['user_id'];

        return 'payment:cooldown:'.hash('sha256', (string) $actor);
    }

    private function lockKey(Request $request, array $context): string
    {
        return 'payment:lock:'.hash('sha256', implode('|', [
            (string) $context['user_id'], (string) $context['payment_type'], (string) $request->input('media_type'),
            (string) $request->input('media_id'), (string) $request->input('subscription_plan_id'),
        ]));
    }
}
