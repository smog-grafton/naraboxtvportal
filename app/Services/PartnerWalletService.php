<?php

namespace App\Services;

use App\Models\CreatorLedgerEntry;
use App\Models\CreatorWallet;
use App\Models\PartnerEarning;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PartnerWalletService
{
    public function __construct(private readonly CreatorWalletService $wallets)
    {
    }

    public function wallet(User $user): CreatorWallet
    {
        return $this->wallets->wallet($user);
    }

    public function balances(User $user): array
    {
        $wallet = $this->wallet($user);
        $sums = $this->partnerEntries($wallet)
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

    public function postCommission(User $user, PartnerEarning $earning): void
    {
        $amount = (int) $earning->partner_amount_minor;
        if ($amount <= 0) {
            return;
        }

        $this->wallets->post(
            $user,
            $amount,
            'partner_commission',
            'pending',
            'partner-earning:'.$earning->id,
            PartnerEarning::class,
            $earning->id,
            'Partner commission generated from a settled NaraBox transaction.',
            $earning->metadata ?? [],
            null,
            $earning->available_at
        );
    }

    public function markAvailable(): int
    {
        $earnings = PartnerEarning::query()
            ->where('status', 'pending')
            ->whereNotNull('available_at')
            ->where('available_at', '<=', now())
            ->get();
        $count = 0;

        foreach ($earnings as $earning) {
            DB::transaction(function () use ($earning, &$count): void {
                $locked = PartnerEarning::query()->lockForUpdate()->find($earning->id);
                if (! $locked || $locked->status !== 'pending') {
                    return;
                }
                $locked->update(['status' => 'available']);
                $this->makeAvailable($locked->user, $locked);
                $count++;
            });
        }

        return $count;
    }

    public function makeAvailable(User $user, PartnerEarning $earning): void
    {
        $amount = (int) $earning->partner_amount_minor;
        if ($amount <= 0) {
            return;
        }

        DB::transaction(function () use ($user, $earning, $amount): void {
            $this->wallets->post(
                $user,
                -$amount,
                'partner_earning_release',
                'pending',
                'partner-earning:'.$earning->id.':pending-release',
                PartnerEarning::class,
                $earning->id,
                'Pending partner commission released.'
            );
            $this->wallets->post(
                $user,
                $amount,
                'partner_earning_release',
                'available',
                'partner-earning:'.$earning->id.':available-credit',
                PartnerEarning::class,
                $earning->id,
                'Partner commission is available.'
            );
        });
    }

    public function reverse(User $user, PartnerEarning $earning, int $refundMinor, string $reference): void
    {
        if ($refundMinor <= 0 || trim($reference) === '') {
            throw new \InvalidArgumentException('A positive refund amount and provider reference are required.');
        }

        DB::transaction(function () use ($user, $earning, $refundMinor, $reference): void {
            $locked = PartnerEarning::query()->lockForUpdate()->findOrFail($earning->id);
            $metadata = $locked->metadata ?? [];
            $gross = max(1, (int) $locked->gross_amount_minor);
            $priorRefund = max(0, (int) ($metadata['reversed_refund_minor'] ?? 0));
            $targetRefund = min($gross, $priorRefund + $refundMinor);
            $targetReversal = $targetRefund >= $gross
                ? (int) $locked->partner_amount_minor
                : intdiv((int) $locked->partner_amount_minor * $targetRefund, $gross);
            $alreadyReversed = max(0, (int) ($metadata['partner_reversed_minor'] ?? 0));
            $reversal = max(0, $targetReversal - $alreadyReversed);
            if ($reversal === 0) {
                return;
            }

            $bucket = $locked->status === 'pending' ? 'pending' : 'available';
            $key = 'partner-refund:'.$locked->transaction_id.':'.hash('sha256', $reference);
            $this->wallets->post(
                $user,
                -$reversal,
                'partner_earning_reversal',
                $bucket,
                $key.':debit',
                PartnerEarning::class,
                $locked->id,
                'Partner commission reversed after a payment refund.',
                ['refund_reference' => $reference, 'refund_amount_minor' => $refundMinor]
            );
            $this->wallets->post(
                $user,
                $reversal,
                'partner_earning_reversal',
                'reversed',
                $key.':record',
                PartnerEarning::class,
                $locked->id,
                'Recorded partner commission reversal.',
                ['refund_reference' => $reference, 'refund_amount_minor' => $refundMinor]
            );

            $metadata['reversed_refund_minor'] = $targetRefund;
            $metadata['partner_reversed_minor'] = $targetReversal;
            $metadata['last_refund_reference'] = $reference;
            $locked->update([
                'status' => $targetReversal >= (int) $locked->partner_amount_minor ? 'reversed' : $locked->status,
                'reversed_at' => $targetReversal >= (int) $locked->partner_amount_minor ? now() : null,
                'metadata' => $metadata,
            ]);
        });
    }

    public function reserve(User $user, int $amountMinor, string $reference): void
    {
        DB::transaction(function () use ($user, $amountMinor, $reference): void {
            $wallet = CreatorWallet::query()->where('user_id', $user->id)->lockForUpdate()->first()
                ?: $this->wallet($user);
            if ($wallet->status !== 'active') {
                throw ValidationException::withMessages(['amount' => ['Partner withdrawals are unavailable while the wallet is on hold.']]);
            }

            $available = (int) $this->partnerEntries($wallet)
                ->where('bucket', 'available')
                ->sum('amount_minor');
            if ($available < $amountMinor) {
                throw ValidationException::withMessages(['amount' => ['Insufficient available partner balance.']]);
            }

            $this->wallets->post(
                $user, -$amountMinor, 'partner_withdrawal_reservation', 'available',
                'partner-withdrawal:'.$reference.':available-debit',
                'partner_withdrawal', $reference
            );
            $this->wallets->post(
                $user, $amountMinor, 'partner_withdrawal_reservation', 'reserved',
                'partner-withdrawal:'.$reference.':reserved-credit',
                'partner_withdrawal', $reference
            );
        });
    }

    public function completeReservation(User $user, int $amountMinor, string $reference): void
    {
        DB::transaction(function () use ($user, $amountMinor, $reference): void {
            $this->wallets->post(
                $user, -$amountMinor, 'partner_withdrawal_completion', 'reserved',
                'partner-withdrawal:'.$reference.':reserved-debit', 'partner_withdrawal', $reference
            );
            $this->wallets->post(
                $user, $amountMinor, 'partner_withdrawal_completion', 'paid',
                'partner-withdrawal:'.$reference.':paid-credit', 'partner_withdrawal', $reference
            );
        });
    }

    public function releaseReservation(User $user, int $amountMinor, string $reference): void
    {
        DB::transaction(function () use ($user, $amountMinor, $reference): void {
            $reserved = (int) $this->partnerEntries($this->wallet($user))
                ->where('bucket', 'reserved')
                ->where('reference_type', 'partner_withdrawal')
                ->where('reference_id', $reference)
                ->sum('amount_minor');
            if ($reserved <= 0) {
                return;
            }

            $this->wallets->post(
                $user, -$amountMinor, 'partner_withdrawal_release', 'reserved',
                'partner-withdrawal:'.$reference.':release-reserved', 'partner_withdrawal', $reference
            );
            $this->wallets->post(
                $user, $amountMinor, 'partner_withdrawal_release', 'available',
                'partner-withdrawal:'.$reference.':release-available', 'partner_withdrawal', $reference
            );
        });
    }

    private function partnerEntries(CreatorWallet $wallet): \Illuminate\Database\Eloquent\Builder
    {
        return CreatorLedgerEntry::query()
            ->where('wallet_id', $wallet->id)
            ->where('status', 'posted')
            ->whereIn('reference_type', [PartnerEarning::class, 'partner_withdrawal', 'partner_adjustment']);
    }
}
