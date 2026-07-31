<?php

namespace App\Services;

use App\Models\CreatorPayoutAttempt;
use App\Models\CreatorPayoutMethod;
use App\Models\CreatorPayoutOption;
use App\Models\CreatorWithdrawalRequest;
use App\Models\FinancialSetting;
use App\Models\PaymentGateway;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WithdrawalService
{
    public function __construct(
        private readonly CreatorWalletService $wallets,
        private readonly CreatorAccessService $access,
        private readonly IoTeCService $iotecService,
        private readonly CreatorAuditService $audit,
        private readonly CreatorCommunicationService $communications
    ) {}

    public function requestWithdrawal(
        User $user,
        CreatorPayoutMethod $payoutMethod,
        int|string|float $amount,
        ?string $idempotencyKey = null
    ): CreatorWithdrawalRequest {
        if (! $this->access->canWithdraw($user)) {
            throw ValidationException::withMessages([
                'withdrawal' => ['Identity verification, monetization and withdrawal permission are required.'],
            ]);
        }

        $settings = FinancialSetting::current();
        if (! $settings) {
            throw ValidationException::withMessages(['amount' => ['Financial settings are not configured.']]);
        }

        $amountMinor = MoneyService::toMinor($amount);
        $min = (int) ($settings->min_withdrawal_minor ?? MoneyService::toMinor((string) $settings->min_withdrawal_amount));
        $max = $settings->max_withdrawal_minor !== null ? (int) $settings->max_withdrawal_minor : null;
        if ($amountMinor < $min || ($max !== null && $amountMinor > $max)) {
            throw ValidationException::withMessages([
                'amount' => [$max
                    ? "Withdrawal must be between {$min} and {$max} UGX."
                    : "Minimum withdrawal amount is {$min} UGX."],
            ]);
        }

        if ($payoutMethod->user_id !== $user->id || ! $payoutMethod->is_verified) {
            throw ValidationException::withMessages([
                'payout_method_id' => ['Use a verified payout method belonging to your account.'],
            ]);
        }
        if ($payoutMethod->withdrawal_hold_until?->isFuture()) {
            throw ValidationException::withMessages([
                'payout_method_id' => ['Withdrawals are temporarily held after payout-detail changes.'],
            ]);
        }

        $idempotencyKey = $idempotencyKey ?: 'withdrawal-request:'.$user->id.':'.Str::uuid();
        $existing = CreatorWithdrawalRequest::where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return $existing;
        }

        $hasPending = CreatorWithdrawalRequest::where('user_id', $user->id)
            ->whereIn('status', [
                CreatorWithdrawalRequest::STATUS_PENDING,
                CreatorWithdrawalRequest::STATUS_UNDER_REVIEW,
                CreatorWithdrawalRequest::STATUS_APPROVED,
                CreatorWithdrawalRequest::STATUS_PROCESSING,
            ])
            ->exists();
        if ($hasPending) {
            throw ValidationException::withMessages(['amount' => ['You already have a withdrawal in progress.']]);
        }

        $this->enforcePeriodLimits($user, $amountMinor, $settings);

        return DB::transaction(function () use ($user, $payoutMethod, $amountMinor, $idempotencyKey) {
            $reference = 'WDR-'.strtoupper(Str::random(12));
            $request = CreatorWithdrawalRequest::create([
                'user_id' => $user->id,
                'payout_method_id' => $payoutMethod->id,
                'amount' => MoneyService::fromMinor($amountMinor),
                'amount_minor' => $amountMinor,
                'currency' => 'UGX',
                'status' => CreatorWithdrawalRequest::STATUS_PENDING,
                'reference' => $reference,
                'idempotency_key' => $idempotencyKey,
                'requested_at' => now(),
            ]);
            $this->wallets->reserve($user, $amountMinor, $reference);
            $this->audit->record('creator.withdrawal_requested', $user, $user, $request, [], [
                'amount_minor' => $amountMinor,
                'currency' => 'UGX',
                'payout_method_id' => $payoutMethod->id,
            ]);
            $message = "Your withdrawal {$reference} is awaiting administrator review.";
            $this->communications->notify(
                $user,
                "creator-withdrawal:{$request->id}:requested",
                'creator_withdrawal_requested',
                'Withdrawal request received',
                $message,
                '/creator/finance/withdraw',
                ['message' => $message],
                'creator_payout'
            );

            return $request;
        });
    }

    public function approve(CreatorWithdrawalRequest $request, User $adminUser): void
    {
        if (! $adminUser->isAdmin()) {
            throw ValidationException::withMessages(['approval' => ['Only administrators may approve withdrawals.']]);
        }
        if (! in_array($request->status, [
            CreatorWithdrawalRequest::STATUS_PENDING,
            CreatorWithdrawalRequest::STATUS_UNDER_REVIEW,
        ], true)) {
            return;
        }

        $request->update([
            'status' => CreatorWithdrawalRequest::STATUS_APPROVED,
            'approved_by' => $adminUser->id,
            'approved_at' => now(),
        ]);
        $this->audit->record('creator.withdrawal_approved', $adminUser, $request->user, $request);
        $message = "Your withdrawal {$request->reference} was approved and is ready for processing.";
        $this->communications->notify(
            $request->user,
            "creator-withdrawal:{$request->id}:approved",
            'creator_withdrawal_approved',
            'Withdrawal approved',
            $message,
            '/creator/finance/withdraw',
            ['message' => $message],
            'creator_payout'
        );
    }

    public function process(CreatorWithdrawalRequest $request): void
    {
        $request->refresh();
        if ($request->status !== CreatorWithdrawalRequest::STATUS_APPROVED) {
            return;
        }

        $request->update(['status' => CreatorWithdrawalRequest::STATUS_PROCESSING]);
        $settings = FinancialSetting::current();
        $method = $request->payoutMethod;
        $iotec = CreatorPayoutOption::query()->where('key', 'iotec')->first();
        if ($settings?->auto_payout_enabled
            && $settings?->iotec_disbursement_enabled
            && $iotec?->is_enabled
            && $iotec?->is_configured
            && $method?->is_verified) {
            $this->initiateIoTec($request, $method);

            return;
        }

        if (! $settings?->manual_payout_enabled) {
            $this->markFailed($request, 'No enabled payout route is available. An administrator must update the payout configuration.');

            return;
        }

        CreatorPayoutAttempt::create([
            'withdrawal_request_id' => $request->id,
            'gateway' => 'manual',
            'gateway_request' => ['reference' => $request->reference],
            'gateway_response' => ['status' => 'awaiting_manual_confirmation'],
            'status' => 'pending',
            'attempted_at' => now(),
            'notes' => 'An administrator must record the external payment reference and confirm settlement.',
        ]);
        $request->update(['gateway_used' => 'manual', 'provider_status' => 'awaiting_manual_confirmation']);
        $message = "Your withdrawal {$request->reference} is being prepared for manual payout.";
        $this->communications->notify(
            $request->user,
            "creator-withdrawal:{$request->id}:processing",
            'creator_withdrawal_processing',
            'Withdrawal is processing',
            $message,
            '/creator/finance/withdraw',
            ['message' => $message],
            'creator_payout'
        );
    }

    private function initiateIoTec(CreatorWithdrawalRequest $request, CreatorPayoutMethod $method): void
    {
        $amountMinor = $this->amountMinor($request);
        if ($amountMinor < 500) {
            $this->markFailed($request, 'ioTec minimum disbursement is 500 UGX.');

            return;
        }

        if ($method->method_type === 'mobile_money') {
            $phone = $method->destination('phone_number');
            if (! $phone || ! IoTeCService::validatePhone($phone)) {
                $this->markFailed($request, 'Invalid Uganda mobile-money destination.');

                return;
            }
            $result = $this->iotec()->disburse(
                $request->reference,
                $amountMinor,
                IoTeCService::normalizePhone($phone),
                $method->destination('account_name') ?? $request->user->name,
                "Creator withdrawal {$request->reference}"
            );
            $safeDestination = IoTeCService::maskPhone($phone);
        } elseif ($method->method_type === 'bank') {
            $accountName = $method->destination('account_name');
            $accountNumber = $method->destination('account_number');
            if (! $accountName || ! $accountNumber) {
                $this->markFailed($request, 'Invalid bank payout destination.');

                return;
            }
            $metadata = $method->metadata ?? [];
            $result = $this->iotec()->bankDisburse(
                $request->reference,
                $amountMinor,
                $accountName,
                $accountNumber,
                $metadata['bank_id'] ?? null,
                $method->bank_code ?? ($metadata['bank_identification_code'] ?? null)
            );
            $safeDestination = '•••• '.substr($accountNumber, -4);
        } else {
            $this->markFailed($request, 'Unsupported payout method.');

            return;
        }

        $attempt = CreatorPayoutAttempt::create([
            'withdrawal_request_id' => $request->id,
            'gateway' => 'iotec',
            'gateway_request' => [
                'destination' => $safeDestination,
                'amount_minor' => $amountMinor,
                'currency' => 'UGX',
                'external_id' => $request->reference,
            ],
            'gateway_response' => $result,
            'status' => isset($result['error']) ? 'failed' : 'accepted',
            'external_id' => $result['request_id'] ?? null,
            'attempted_at' => now(),
        ]);

        if (isset($result['error'])) {
            $this->markFailed($request, (string) $result['error']);

            return;
        }

        // An accepted ioTec request is still processing. Only reconciliation or a
        // verified callback may move the withdrawal to paid.
        $request->update([
            'status' => CreatorWithdrawalRequest::STATUS_PROCESSING,
            'gateway_used' => 'iotec',
            'gateway_reference' => $result['request_id'],
            'provider_status' => $result['status'] ?? 'Pending',
            'meta' => array_merge($request->meta ?? [], ['payout_attempt_id' => $attempt->id]),
        ]);
        $message = "Your withdrawal {$request->reference} was accepted by the payout provider and is processing.";
        $this->communications->notify(
            $request->user,
            "creator-withdrawal:{$request->id}:processing",
            'creator_withdrawal_processing',
            'Withdrawal is processing',
            $message,
            '/creator/finance/withdraw',
            ['message' => $message],
            'creator_payout'
        );
    }

    public function reconcile(CreatorWithdrawalRequest $request): CreatorWithdrawalRequest
    {
        if ($request->status !== CreatorWithdrawalRequest::STATUS_PROCESSING
            || $request->gateway_used !== 'iotec'
            || ! $request->gateway_reference) {
            return $request;
        }

        $result = $this->iotec()->getDisbursementStatus($request->gateway_reference);
        $request->update([
            'provider_status' => $result['status'] ?? ($result['error'] ?? 'StatusCheckFailed'),
            'last_reconciled_at' => now(),
        ]);
        $request->payoutAttempts()->create([
            'gateway' => 'iotec',
            'gateway_request' => ['status_for' => $request->gateway_reference],
            'gateway_response' => $result,
            'status' => 'reconciliation',
            'external_id' => $request->gateway_reference,
            'attempted_at' => now(),
        ]);

        if (($result['normalized'] ?? null) === 'success') {
            $this->confirmPaid($request, $request->gateway_reference, null, true);
        } elseif (($result['normalized'] ?? null) === 'failed') {
            $this->markFailed($request, (string) ($result['error'] ?? $result['status'] ?? 'Provider rejected the payout.'));
        }

        return $request->refresh();
    }

    public function applyProviderStatus(string $providerReference, string $status, array $payload): ?CreatorWithdrawalRequest
    {
        $request = CreatorWithdrawalRequest::where('gateway_reference', $providerReference)->first();
        if (! $request) {
            return null;
        }

        $normalized = strtolower($status);
        if ($normalized === 'success') {
            $this->confirmPaid($request, $providerReference, null, true);
        } elseif (in_array($normalized, ['failed', 'cancelled', 'rejected', 'rolledback'], true)) {
            $this->markFailed($request, 'Provider status: '.$status);
        } else {
            $request->update(['provider_status' => $status, 'last_reconciled_at' => now()]);
        }
        $this->audit->record('creator.withdrawal_provider_callback', null, $request->user, $request, [], [
            'provider_status' => $status,
            'payload_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
        ]);

        return $request->refresh();
    }

    public function confirmPaid(
        CreatorWithdrawalRequest $request,
        string $externalReference,
        ?User $admin = null,
        bool $providerConfirmed = false
    ): void {
        if ($request->status === CreatorWithdrawalRequest::STATUS_PAID) {
            return;
        }
        if ($request->status !== CreatorWithdrawalRequest::STATUS_PROCESSING) {
            throw ValidationException::withMessages(['withdrawal' => ['Only processing withdrawals can be confirmed paid.']]);
        }
        if (! $providerConfirmed && ! $admin?->isAdmin()) {
            throw ValidationException::withMessages(['withdrawal' => ['Administrator confirmation is required.']]);
        }

        DB::transaction(function () use ($request, $externalReference, $admin, $providerConfirmed) {
            $locked = CreatorWithdrawalRequest::query()->lockForUpdate()->findOrFail($request->id);
            if ($locked->status === CreatorWithdrawalRequest::STATUS_PAID) {
                return;
            }
            $this->wallets->completeReservation($locked->user, $this->amountMinor($locked), $locked->reference);
            $locked->update([
                'status' => CreatorWithdrawalRequest::STATUS_PAID,
                'processed_at' => now(),
                'provider_confirmed_at' => $providerConfirmed ? now() : null,
                'provider_status' => $providerConfirmed ? 'Success' : 'manually_confirmed',
                'gateway_reference' => $externalReference,
            ]);
            $this->audit->record('creator.withdrawal_paid', $admin, $locked->user, $locked, [], [
                'external_reference' => $externalReference,
                'provider_confirmed' => $providerConfirmed,
            ]);
            $message = "Withdrawal {$locked->reference} was confirmed paid.";
            $this->communications->notify(
                $locked->user,
                "creator-withdrawal:{$locked->id}:paid",
                'creator_withdrawal_paid',
                'Withdrawal paid',
                $message,
                '/creator/finance/withdraw',
                ['message' => $message],
                'creator_payout'
            );
        });
    }

    public function markFailed(CreatorWithdrawalRequest $request, string $reason): void
    {
        if (in_array($request->status, [
            CreatorWithdrawalRequest::STATUS_FAILED,
            CreatorWithdrawalRequest::STATUS_REJECTED,
            CreatorWithdrawalRequest::STATUS_CANCELLED,
            CreatorWithdrawalRequest::STATUS_PAID,
        ], true)) {
            return;
        }
        DB::transaction(function () use ($request, $reason) {
            $this->wallets->releaseReservation($request->user, $this->amountMinor($request), $request->reference);
            $request->update([
                'status' => CreatorWithdrawalRequest::STATUS_FAILED,
                'failure_reason' => $reason,
                'processed_at' => now(),
            ]);
            $this->communications->notify(
                $request->user,
                "creator-withdrawal:{$request->id}:failed:".sha1($reason),
                'creator_withdrawal_failed',
                'Withdrawal needs attention',
                $reason,
                '/creator/finance/withdraw',
                ['message' => $reason],
                'creator_payout'
            );
        });
    }

    public function reject(CreatorWithdrawalRequest $request, string $reason): void
    {
        if (! in_array($request->status, [
            CreatorWithdrawalRequest::STATUS_PENDING,
            CreatorWithdrawalRequest::STATUS_UNDER_REVIEW,
            CreatorWithdrawalRequest::STATUS_APPROVED,
        ], true)) {
            return;
        }
        DB::transaction(function () use ($request, $reason) {
            $this->wallets->releaseReservation($request->user, $this->amountMinor($request), $request->reference);
            $request->update([
                'status' => CreatorWithdrawalRequest::STATUS_REJECTED,
                'failure_reason' => $reason,
                'admin_notes' => $reason,
            ]);
            $this->communications->notify(
                $request->user,
                "creator-withdrawal:{$request->id}:rejected:".sha1($reason),
                'creator_withdrawal_rejected',
                'Withdrawal request update',
                $reason,
                '/creator/finance/withdraw',
                ['message' => $reason],
                'creator_payout'
            );
        });
    }

    public function cancel(CreatorWithdrawalRequest $request): void
    {
        if (! $request->isCancellable()) {
            throw ValidationException::withMessages(['withdrawal' => ['This withdrawal cannot be cancelled.']]);
        }
        DB::transaction(function () use ($request) {
            $this->wallets->releaseReservation($request->user, $this->amountMinor($request), $request->reference);
            $request->update(['status' => CreatorWithdrawalRequest::STATUS_CANCELLED]);
        });
    }

    private function amountMinor(CreatorWithdrawalRequest $request): int
    {
        return (int) ($request->amount_minor ?? MoneyService::toMinor((string) $request->amount));
    }

    private function iotec(): IoTeCService
    {
        $gateway = PaymentGateway::query()->where('slug', 'iotec')->first();

        return $gateway ? new IoTeCService($gateway) : $this->iotecService;
    }

    private function enforcePeriodLimits(User $user, int $amountMinor, FinancialSetting $settings): void
    {
        $statuses = [
            CreatorWithdrawalRequest::STATUS_PENDING,
            CreatorWithdrawalRequest::STATUS_UNDER_REVIEW,
            CreatorWithdrawalRequest::STATUS_APPROVED,
            CreatorWithdrawalRequest::STATUS_PROCESSING,
            CreatorWithdrawalRequest::STATUS_PAID,
        ];
        $today = (int) CreatorWithdrawalRequest::where('user_id', $user->id)
            ->whereIn('status', $statuses)
            ->whereDate('requested_at', today())
            ->sum('amount_minor');
        $month = (int) CreatorWithdrawalRequest::where('user_id', $user->id)
            ->whereIn('status', $statuses)
            ->whereBetween('requested_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('amount_minor');

        if ($settings->daily_withdrawal_limit_minor !== null
            && $today + $amountMinor > (int) $settings->daily_withdrawal_limit_minor) {
            throw ValidationException::withMessages(['amount' => ['This request exceeds your daily withdrawal limit.']]);
        }
        if ($settings->monthly_withdrawal_limit_minor !== null
            && $month + $amountMinor > (int) $settings->monthly_withdrawal_limit_minor) {
            throw ValidationException::withMessages(['amount' => ['This request exceeds your monthly withdrawal limit.']]);
        }
    }
}
