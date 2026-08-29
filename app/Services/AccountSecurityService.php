<?php

namespace App\Services;

use App\Models\AccountEnforcement;
use App\Models\SecurityIdentity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class AccountSecurityService
{
    public function __construct(
        private readonly SecurityEventService $events,
        private readonly SecurityRuleService $rules,
    ) {}

    public function effectiveStatus(User $user): string
    {
        $status = strtoupper((string) ($user->account_status ?: 'ACTIVE'));
        if (in_array($status, ['SUSPENDED', 'BANNED'], true)
            && $user->status_expires_at?->isPast()) {
            $this->setStatus($user, 'ACTIVE', 'Temporary enforcement expired.', null, 'AUTOMATIC', null);

            return 'ACTIVE';
        }

        return $status;
    }

    public function blockingResponse(User $user, bool $paymentOnly = false): ?\Illuminate\Http\JsonResponse
    {
        $status = $this->effectiveStatus($user);
        if (in_array($status, ['BANNED', 'SUSPENDED'], true)) {
            return response()->json([
                'message' => 'This account is temporarily unavailable. Please contact support.',
                'code' => 'ACCOUNT_UNAVAILABLE',
            ], 403);
        }
        if ($paymentOnly && $status === 'PAYMENT_RESTRICTED') {
            return response()->json([
                'message' => 'Payment initiation is temporarily unavailable for this account.',
                'code' => 'PAYMENT_RESTRICTED',
            ], 403);
        }

        return null;
    }

    public function restrictPayments(User $user, string $reason, ?string $notes = null, string $source = 'MANUAL', ?User $actor = null, array $context = []): void
    {
        if ($source === 'AUTOMATIC' && $user->isAdmin()) {
            return;
        }
        $this->setStatus($user, 'PAYMENT_RESTRICTED', $reason, $notes, $source, $actor, $context);

        if ($source === 'AUTOMATIC') {
            $expires = now()->addDay();
            if (! empty($context['provider_user_id'])) {
                $providerType = match (strtolower((string) ($context['provider'] ?? ''))) {
                    'google' => 'GOOGLE_SUB',
                    'apple' => 'APPLE_SUB',
                    default => 'SOCIAL_PROVIDER_ID',
                };
                $this->rules->storeIdentity($providerType, (string) $context['provider_user_id'], 'BLOCK_PAYMENT', $reason, [
                    'source' => 'AUTOMATIC', 'expires_at' => $expires, 'risk_level' => 'CRITICAL',
                ]);
            }
            if (! empty($context['device_id'])) {
                $this->rules->storeIdentity('DEVICE_ID', (string) $context['device_id'], 'BLOCK_PAYMENT', $reason, [
                    'source' => 'AUTOMATIC', 'expires_at' => $expires, 'risk_level' => 'HIGH',
                ]);
            }
            if (! empty($context['ip_address'])) {
                $this->rules->storeIdentity('IP', (string) $context['ip_address'], 'BLOCK_PAYMENT', $reason, [
                    'source' => 'AUTOMATIC', 'expires_at' => now()->addHour(), 'risk_level' => 'ELEVATED',
                ]);
            }
        }
    }

    public function suspend(User $user, string $reason, ?string $notes, ?\DateTimeInterface $until, ?User $actor): void
    {
        $this->assertAdminSafety($user, $actor, 'suspend');
        $this->setStatus($user, 'SUSPENDED', $reason, $notes, 'MANUAL', $actor, [], $until);
        $this->revokeSessions($user);
    }

    public function ban(User $user, string $reason, ?string $notes, ?\DateTimeInterface $until, ?User $actor): void
    {
        $this->assertAdminSafety($user, $actor, 'ban');
        $this->setStatus($user, 'BANNED', $reason, $notes, 'MANUAL', $actor, [], $until);
        $this->rules->storeIdentity('EMAIL', $user->email, 'BLOCK_LOGIN', $reason, [
            'notes' => $notes, 'created_by' => $actor?->id, 'expires_at' => $until, 'risk_level' => 'CRITICAL', 'source' => 'ACCOUNT_BAN',
        ]);
        if ($user->phone) {
            $this->rules->storeIdentity('PHONE', $user->phone, 'BLOCK_LOGIN', $reason, [
                'notes' => $notes, 'created_by' => $actor?->id, 'expires_at' => $until, 'risk_level' => 'CRITICAL', 'source' => 'ACCOUNT_BAN',
            ]);
        }
        foreach ($user->socialAccounts()->get() as $social) {
            $this->rules->storeIdentity(strtoupper($social->provider).'_SUB', $social->provider_user_id, 'BLOCK_LOGIN', $reason, [
                'notes' => $notes, 'created_by' => $actor?->id, 'expires_at' => $until, 'risk_level' => 'CRITICAL', 'source' => 'ACCOUNT_BAN',
            ]);
        }
        $this->revokeSessions($user);
    }

    public function activate(User $user, string $reason, ?string $notes, ?User $actor): void
    {
        $this->setStatus($user, 'ACTIVE', $reason, $notes, 'MANUAL', $actor);
        $this->clearRestoredIdentityBlocks($user);
    }

    public function revokeSessions(User $user): void
    {
        $user->tokens()->delete();
        if (Schema::hasTable('sessions')) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }
        if (Schema::hasTable('web_bridge_tokens')) {
            DB::table('web_bridge_tokens')->where('user_id', $user->id)->whereNull('used_at')->update(['used_at' => now()]);
        }
        if (Schema::hasTable('tv_device_codes')) {
            DB::table('tv_device_codes')->where('user_id', $user->id)->where('status', 'APPROVED')->update(['status' => 'EXPIRED']);
        }
    }

    public function enforceMatchedAction(User $user, string $action, string $reason, array $context = []): void
    {
        $action = strtoupper($action);
        if ($user->isAdmin()) {
            return;
        }

        if ($action === 'PAYMENT_RESTRICT') {
            $this->restrictPayments($user, $reason, null, 'AUTOMATIC', null, $context);
        } elseif ($action === 'SUSPEND') {
            $this->suspend($user, $reason, 'Applied by a configured security rule.', now()->addDay(), null);
        } elseif ($action === 'BAN') {
            $this->ban($user, $reason, 'Applied by a configured security rule.', null, null);
        }
    }

    private function setStatus(User $user, string $status, string $reason, ?string $notes, string $source, ?User $actor, array $context = [], ?\DateTimeInterface $until = null): void
    {
        DB::transaction(function () use ($user, $status, $reason, $notes, $source, $actor, $context, $until): void {
            $previous = $user->account_status ?: 'ACTIVE';
            AccountEnforcement::where('user_id', $user->id)->whereNull('ended_at')->update(['ended_at' => now()]);
            $event = $this->events->record('ACCOUNT_'.($status === 'PAYMENT_RESTRICTED' ? 'PAYMENT_RESTRICTED' : $status), [
                'user_id' => $user->id,
                'risk_level' => in_array($status, ['BANNED', 'PAYMENT_RESTRICTED'], true) ? 'CRITICAL' : 'HIGH',
                'action' => $status,
                'reason' => $reason,
                'actor_user_id' => $actor?->id,
                'metadata' => ['source' => $source, 'notes' => $notes] + $context,
            ]);
            $user->forceFill([
                'account_status' => $status,
                'security_reason' => $reason,
                'security_notes' => $notes,
                'status_started_at' => now(),
                'status_expires_at' => $until,
                'status_changed_by' => $actor?->id,
                'risk_level' => $status === 'ACTIVE' ? 'NORMAL' : ($status === 'SUSPENDED' ? 'HIGH' : 'CRITICAL'),
            ])->save();
            AccountEnforcement::create([
                'user_id' => $user->id,
                'action' => $status,
                'previous_status' => $previous,
                'new_status' => $status,
                'reason' => $reason,
                'notes' => $notes,
                'source' => $source,
                'actor_user_id' => $actor?->id,
                'security_event_id' => $event?->id,
                'started_at' => now(),
                'expires_at' => $until,
            ]);
        });
    }

    private function clearRestoredIdentityBlocks(User $user): void
    {
        $identities = array_filter([
            ['EMAIL', $user->email, 'ACCOUNT_BAN'],
            ['PHONE', $user->phone, 'ACCOUNT_BAN'],
            ['DEVICE_ID', $user->registration_device_id, 'AUTOMATIC'],
        ], fn (array $identity) => filled($identity[1]));
        foreach ($user->socialAccounts()->get() as $social) {
            $identities[] = [strtoupper($social->provider).'_SUB', $social->provider_user_id, 'ACCOUNT_BAN'];
            $identities[] = [strtoupper($social->provider).'_SUB', $social->provider_user_id, 'AUTOMATIC'];
        }
        foreach ($user->paymentAttempts()->whereNotNull('device_id')->distinct()->pluck('device_id') as $deviceId) {
            $identities[] = ['DEVICE_ID', $deviceId, 'AUTOMATIC'];
        }

        foreach ($identities as [$type, $value, $source]) {
            $normalized = app(IdentityNormalizer::class)->forType($type, (string) $value);
            SecurityIdentity::where('identity_type', $type)
                ->where('value_hash', hash('sha256', $normalized))
                ->where('source', $source)
                ->update(['enabled' => false]);
        }
    }

    private function assertAdminSafety(User $target, ?User $actor, string $verb): void
    {
        if (! $target->isAdmin()) {
            return;
        }
        if ($actor && $target->is($actor)) {
            throw new InvalidArgumentException("Administrators cannot {$verb} their own account.");
        }
        if (User::whereHas('role', fn ($q) => $q->where('name', 'admin'))
            ->where('account_status', 'ACTIVE')->count() <= 1) {
            throw new InvalidArgumentException("The final active administrator cannot be {$verb}ed.");
        }
    }
}
