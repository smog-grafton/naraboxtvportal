<?php

namespace App\Services;

use App\Models\CreatorLedgerEntry;
use App\Models\CreatorWallet;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreatorWalletService
{
    public function wallet(User $user, string $currency = 'UGX'): CreatorWallet
    {
        return CreatorWallet::firstOrCreate(
            ['user_id' => $user->id],
            ['currency' => strtoupper($currency), 'status' => 'active']
        );
    }

    public function post(
        User $user,
        int $amountMinor,
        string $entryType,
        string $bucket,
        string $idempotencyKey,
        ?string $referenceType = null,
        string|int|null $referenceId = null,
        ?string $description = null,
        array $metadata = [],
        ?User $createdBy = null,
        ?\DateTimeInterface $availableAt = null
    ): CreatorLedgerEntry {
        if ($amountMinor === 0) {
            throw new \InvalidArgumentException('Ledger entries cannot have a zero amount.');
        }

        return DB::transaction(function () use (
            $user,
            $amountMinor,
            $entryType,
            $bucket,
            $idempotencyKey,
            $referenceType,
            $referenceId,
            $description,
            $metadata,
            $createdBy,
            $availableAt
        ) {
            $existing = CreatorLedgerEntry::where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }

            $wallet = CreatorWallet::query()->where('user_id', $user->id)->lockForUpdate()->first()
                ?: $this->wallet($user);

            return CreatorLedgerEntry::create([
                'wallet_id' => $wallet->id,
                'user_id' => $user->id,
                'amount_minor' => $amountMinor,
                'currency' => $wallet->currency,
                'entry_type' => $entryType,
                'bucket' => $bucket,
                'status' => 'posted',
                'reference_type' => $referenceType,
                'reference_id' => $referenceId !== null ? (string) $referenceId : null,
                'idempotency_key' => $idempotencyKey,
                'description' => $description,
                'metadata' => $metadata ?: null,
                'available_at' => $availableAt,
                'created_by' => $createdBy?->id,
            ]);
        });
    }

    public function balances(User $user): array
    {
        $wallet = $this->wallet($user);
        $sums = CreatorLedgerEntry::query()
            ->where('wallet_id', $wallet->id)
            ->where('status', 'posted')
            ->selectRaw('bucket, COALESCE(SUM(amount_minor), 0) AS total')
            ->groupBy('bucket')
            ->pluck('total', 'bucket');

        return [
            'currency' => $wallet->currency,
            'pending_minor' => (int) ($sums['pending'] ?? 0),
            'available_minor' => (int) ($sums['available'] ?? 0),
            'on_hold_minor' => (int) ($sums['on_hold'] ?? 0),
            'reserved_minor' => (int) ($sums['reserved'] ?? 0),
            'paid_minor' => (int) ($sums['paid'] ?? 0),
            'reversed_minor' => (int) ($sums['reversed'] ?? 0),
        ];
    }

    public function makeAvailable(User $user, int $amountMinor, string $reference, string $sourceType = 'earning'): void
    {
        DB::transaction(function () use ($user, $amountMinor, $reference, $sourceType) {
            $this->post(
                $user,
                -$amountMinor,
                'earning_release',
                'pending',
                "{$sourceType}:{$reference}:pending-release",
                $sourceType,
                $reference,
                'Pending creator earnings released.'
            );
            $this->post(
                $user,
                $amountMinor,
                'earning_release',
                'available',
                "{$sourceType}:{$reference}:available-credit",
                $sourceType,
                $reference,
                'Creator earnings are available.'
            );
        });
    }

    public function reserve(User $user, int $amountMinor, string $withdrawalReference): void
    {
        DB::transaction(function () use ($user, $amountMinor, $withdrawalReference) {
            $wallet = CreatorWallet::query()->where('user_id', $user->id)->lockForUpdate()->first()
                ?: $this->wallet($user);
            if ($wallet->status !== 'active') {
                throw ValidationException::withMessages([
                    'amount' => ['Withdrawals are unavailable while this creator wallet is on hold.'],
                ]);
            }
            $available = (int) CreatorLedgerEntry::where('wallet_id', $wallet->id)
                ->where('bucket', 'available')
                ->where('status', 'posted')
                ->sum('amount_minor');
            if ($available < $amountMinor) {
                throw ValidationException::withMessages(['amount' => ['Insufficient available balance.']]);
            }

            $this->post(
                $user,
                -$amountMinor,
                'withdrawal_reservation',
                'available',
                "withdrawal:{$withdrawalReference}:available-debit",
                'creator_withdrawal',
                $withdrawalReference
            );
            $this->post(
                $user,
                $amountMinor,
                'withdrawal_reservation',
                'reserved',
                "withdrawal:{$withdrawalReference}:reserved-credit",
                'creator_withdrawal',
                $withdrawalReference
            );
        });
    }

    public function completeReservation(User $user, int $amountMinor, string $withdrawalReference): void
    {
        DB::transaction(function () use ($user, $amountMinor, $withdrawalReference) {
            $this->post(
                $user,
                -$amountMinor,
                'withdrawal_completion',
                'reserved',
                "withdrawal:{$withdrawalReference}:reserved-debit",
                'creator_withdrawal',
                $withdrawalReference
            );
            $this->post(
                $user,
                $amountMinor,
                'withdrawal_completion',
                'paid',
                "withdrawal:{$withdrawalReference}:paid-credit",
                'creator_withdrawal',
                $withdrawalReference
            );
        });
    }

    public function releaseReservation(User $user, int $amountMinor, string $withdrawalReference): void
    {
        DB::transaction(function () use ($user, $amountMinor, $withdrawalReference) {
            $reserved = CreatorLedgerEntry::where('user_id', $user->id)
                ->where('bucket', 'reserved')
                ->where('reference_type', 'creator_withdrawal')
                ->where('reference_id', $withdrawalReference)
                ->sum('amount_minor');
            if ((int) $reserved <= 0) {
                return;
            }

            $this->post(
                $user,
                -$amountMinor,
                'withdrawal_release',
                'reserved',
                "withdrawal:{$withdrawalReference}:release-reserved",
                'creator_withdrawal',
                $withdrawalReference
            );
            $this->post(
                $user,
                $amountMinor,
                'withdrawal_release',
                'available',
                "withdrawal:{$withdrawalReference}:release-available",
                'creator_withdrawal',
                $withdrawalReference
            );
        });
    }
}
