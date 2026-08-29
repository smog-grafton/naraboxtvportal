<?php

namespace App\Services;

use App\Models\CreatorPayoutMethod;
use App\Models\CreatorWithdrawalRequest;
use App\Models\FinancialSetting;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PartnerWithdrawalService
{
    public function __construct(private readonly PartnerWalletService $wallets)
    {
    }

    public function requestWithdrawal(
        User $user,
        Partner $partner,
        CreatorPayoutMethod $payoutMethod,
        int|string|float $amount,
        ?string $idempotencyKey = null
    ): CreatorWithdrawalRequest {
        $amountMinor = MoneyService::toMinor($amount);
        $settings = FinancialSetting::current();
        $minimum = max(
            (int) ($partner->minimum_payout_minor ?? 0),
            (int) ($settings?->min_withdrawal_minor ?? 10000)
        );
        if ($amountMinor < $minimum) {
            throw ValidationException::withMessages(['amount' => ["Minimum partner payout is {$minimum} UGX."]]);
        }
        if ($payoutMethod->user_id !== $user->id || ! $payoutMethod->is_verified) {
            throw ValidationException::withMessages(['payout_method_id' => ['Use a verified payout method belonging to your account.']]);
        }
        if ($payoutMethod->withdrawal_hold_until?->isFuture()) {
            throw ValidationException::withMessages(['payout_method_id' => ['Withdrawals are temporarily held after payout-detail changes.']]);
        }

        $idempotencyKey = $idempotencyKey ?: 'partner-withdrawal-request:'.$user->id.':'.Str::uuid();
        $existing = CreatorWithdrawalRequest::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return $existing;
        }

        $pending = CreatorWithdrawalRequest::query()
            ->where('user_id', $user->id)
            ->where('beneficiary_type', 'partner')
            ->whereIn('status', [
                CreatorWithdrawalRequest::STATUS_PENDING,
                CreatorWithdrawalRequest::STATUS_UNDER_REVIEW,
                CreatorWithdrawalRequest::STATUS_APPROVED,
                CreatorWithdrawalRequest::STATUS_PROCESSING,
            ])
            ->exists();
        if ($pending) {
            throw ValidationException::withMessages(['amount' => ['You already have a partner payout in progress.']]);
        }

        $balance = $this->wallets->balances($user);
        if ($amountMinor > $balance['available_minor']) {
            throw ValidationException::withMessages(['amount' => ['Insufficient available partner balance.']]);
        }

        return DB::transaction(function () use ($user, $payoutMethod, $amountMinor, $idempotencyKey): CreatorWithdrawalRequest {
            $reference = 'PWR-'.strtoupper(Str::random(12));
            $request = CreatorWithdrawalRequest::create([
                'user_id' => $user->id,
                'beneficiary_type' => 'partner',
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

            return $request;
        });
    }
}
