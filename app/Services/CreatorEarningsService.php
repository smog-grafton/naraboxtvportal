<?php

namespace App\Services;

use App\Models\CreatorEarning;
use App\Models\CreatorLedgerEntry;
use App\Models\CreatorPermission;
use App\Models\FinancialSetting;
use App\Models\MediaLibrary;
use App\Models\Movie;
use App\Models\PaymentTransaction;
use App\Models\TVShow;
use App\Models\User;
use App\Models\VJ;
use Illuminate\Support\Facades\DB;

class CreatorEarningsService
{
    public function __construct(
        private readonly CreatorWalletService $wallets,
        private readonly UserNotificationService $notifications
    )
    {
    }

    public function allocateFromTransaction(PaymentTransaction $transaction): ?CreatorEarning
    {
        if (! in_array($transaction->type, ['RENT', 'BUY'], true) || $transaction->status !== 'SUCCESS') {
            return null;
        }

        return DB::transaction(function () use ($transaction) {
            $existing = CreatorEarning::where('transaction_id', $transaction->id)->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }

            $media = $transaction->transactionable;
            if (! $media instanceof Movie && ! $media instanceof TVShow) {
                return null;
            }

            [$creator, $eligibility] = $this->resolveCreator($media);
            if (! $creator || $eligibility !== 'creator_eligible') {
                // The payment remains platform-controlled. No creator liability or wallet
                // entry is created and later claim approval cannot transfer it silently.
                return null;
            }

            $settings = FinancialSetting::current();
            if (! $settings) {
                return null;
            }

            $permission = CreatorPermission::where('user_id', $creator->id)->first();
            $monetization = $media->monetizationSetting;
            $creatorBps = (int) (
                $monetization?->creator_share_bps_override
                ?? $permission?->creator_share_bps_override
                ?? $settings->creator_share_bps
                ?? 7000
            );
            $creatorBps = max(0, min(10000, $creatorBps));
            $platformBps = 10000 - $creatorBps;
            $grossMinor = MoneyService::toMinor((string) $transaction->amount);
            $metadata = $transaction->meta ?? [];
            $refundMinor = max(0, (int) ($metadata['refunded_amount_minor'] ?? 0));
            $chargebackMinor = max(0, (int) ($metadata['chargeback_amount_minor'] ?? 0));
            $gatewayCostMinor = max(0, (int) ($metadata['gateway_cost_minor'] ?? 0));
            $statutoryMinor = max(0, (int) ($metadata['statutory_deductions_minor'] ?? 0));
            $netMinor = max(0, $grossMinor - $refundMinor - $chargebackMinor - $gatewayCostMinor - $statutoryMinor);
            $creatorMinor = MoneyService::share($netMinor, $creatorBps);
            $platformMinor = $netMinor - $creatorMinor;
            $holdDays = (int) $settings->creator_hold_days;
            $availableAt = now()->addDays($holdDays);
            $idempotency = 'payment-earning:'.$transaction->transaction_ref;
            $snapshot = [
                'currency' => 'UGX',
                'gross_minor' => $grossMinor,
                'refund_minor' => $refundMinor,
                'chargeback_minor' => $chargebackMinor,
                'gateway_cost_minor' => $gatewayCostMinor,
                'statutory_deductions_minor' => $statutoryMinor,
                'net_distributable_minor' => $netMinor,
                'creator_share_bps' => $creatorBps,
                'platform_share_bps' => $platformBps,
                'creator_amount_minor' => $creatorMinor,
                'platform_amount_minor' => $platformMinor,
                'rate_source' => $monetization?->creator_share_bps_override !== null
                    ? 'title_override'
                    : ($permission?->creator_share_bps_override !== null ? 'creator_override' : 'platform_default'),
            ];

            $earning = CreatorEarning::create([
                'user_id' => $creator->id,
                'transaction_id' => $transaction->id,
                'earnable_type' => $media::class,
                'earnable_id' => $media->id,
                'gross_amount' => MoneyService::fromMinor($grossMinor),
                'commission_rate' => $platformBps / 100,
                'platform_amount' => MoneyService::fromMinor($platformMinor),
                'creator_amount' => MoneyService::fromMinor($creatorMinor),
                'gross_amount_minor' => $grossMinor,
                'net_amount_minor' => $netMinor,
                'creator_amount_minor' => $creatorMinor,
                'platform_amount_minor' => $platformMinor,
                'creator_share_bps' => $creatorBps,
                'platform_share_bps' => $platformBps,
                'eligibility_status' => 'creator_eligible',
                'calculation_snapshot' => $snapshot,
                'idempotency_key' => $idempotency,
                'status' => 'pending',
                'available_at' => $availableAt,
            ]);

            if ($creatorMinor > 0) {
                $this->wallets->post(
                    $creator,
                    $creatorMinor,
                    $transaction->type === 'RENT' ? 'rent_earning' : 'purchase_earning',
                    'pending',
                    $idempotency,
                    PaymentTransaction::class,
                    $transaction->id,
                    "{$transaction->type} creator earning",
                    $snapshot,
                    null,
                    $availableAt
                );
            }

            return $earning;
        });
    }

    /**
     * @return array{0: User|null, 1: string}
     */
    private function resolveCreator(Movie|TVShow $media): array
    {
        $profile = null;
        if ($media->media_library_id) {
            $profile = $media->mediaLibrary ?? MediaLibrary::find($media->media_library_id);
        } elseif ($media->vj_id) {
            $profile = $media->vj ?? VJ::find($media->vj_id);
        }

        if (! $profile?->user_id) {
            return [null, 'platform_managed'];
        }

        $permission = CreatorPermission::where('user_id', $profile->user_id)->first();
        $eligible = (bool) ($profile->is_verified ?? false)
            && $permission?->identity_verified
            && $permission?->monetization_enabled
            && ! $permission?->is_suspended
            && ! $permission?->is_revoked;

        return [$profile->user, $eligible ? 'creator_eligible' : 'creator_not_eligible'];
    }

    public function getBalance(User $user): array
    {
        $balances = $this->wallets->balances($user);

        return [
            'currency' => $balances['currency'],
            'pending_minor' => $balances['pending_minor'],
            'available_minor' => $balances['available_minor'],
            'on_hold_minor' => $balances['on_hold_minor'],
            'reserved_minor' => $balances['reserved_minor'],
            'paid_minor' => $balances['paid_minor'],
            // Backward-compatible whole-UGX keys.
            'pending' => $balances['pending_minor'],
            'available' => $balances['available_minor'],
            'withdrawn_total' => $balances['paid_minor'],
            'total_earned' => $balances['pending_minor'] + $balances['available_minor']
                + $balances['reserved_minor'] + $balances['paid_minor'],
        ];
    }

    public function markAvailable(): int
    {
        $earnings = CreatorEarning::pending()
            ->where('available_at', '<=', now())
            ->where('eligibility_status', 'creator_eligible')
            ->get();
        $count = 0;

        foreach ($earnings as $earning) {
            DB::transaction(function () use ($earning, &$count) {
                $locked = CreatorEarning::query()->lockForUpdate()->find($earning->id);
                if (! $locked || $locked->status !== 'pending') {
                    return;
                }
                $amount = (int) ($locked->creator_amount_minor ?? MoneyService::toMinor((string) $locked->creator_amount));
                $locked->update(['status' => 'available']);
                if ($amount > 0) {
                    $this->wallets->makeAvailable(
                        $locked->user,
                        $amount,
                        (string) $locked->id,
                        'creator_earning'
                    );
                }
                $count++;
            });
        }

        return $count;
    }

    public function reverseForTransaction(
        PaymentTransaction $transaction,
        int $refundMinor,
        string $refundReference
    ): ?CreatorEarning {
        if ($refundMinor <= 0 || trim($refundReference) === '') {
            throw new \InvalidArgumentException('A positive refund amount and provider reference are required.');
        }

        return DB::transaction(function () use ($transaction, $refundMinor, $refundReference) {
            $earning = CreatorEarning::where('transaction_id', $transaction->id)->lockForUpdate()->first();
            if (! $earning) {
                return null;
            }
            $idempotency = 'payment-refund:'.$transaction->id.':'.hash('sha256', $refundReference);
            if (CreatorLedgerEntry::where('idempotency_key', $idempotency.':debit')->exists()) {
                return $earning;
            }

            $snapshot = $earning->calculation_snapshot ?? [];
            $priorRefundMinor = max(0, (int) ($snapshot['reversed_refund_minor'] ?? 0));
            $grossMinor = max(1, (int) ($earning->gross_amount_minor ?? MoneyService::toMinor((string) $earning->gross_amount)));
            $creatorTotal = max(0, (int) ($earning->creator_amount_minor ?? MoneyService::toMinor((string) $earning->creator_amount)));
            $targetRefundMinor = min($grossMinor, $priorRefundMinor + $refundMinor);
            $targetCreatorReversal = $targetRefundMinor >= $grossMinor
                ? $creatorTotal
                : intdiv($creatorTotal * $targetRefundMinor, $grossMinor);
            $alreadyReversed = max(0, (int) ($snapshot['creator_reversed_minor'] ?? 0));
            $creatorReversal = max(0, $targetCreatorReversal - $alreadyReversed);
            if ($creatorReversal === 0) {
                return $earning;
            }

            $debitBucket = $earning->status === 'pending' ? 'pending' : 'available';
            $metadata = ['refund_reference' => $refundReference, 'refund_amount_minor' => $refundMinor];
            $this->wallets->post(
                $earning->user,
                -$creatorReversal,
                'earning_reversal',
                $debitBucket,
                $idempotency.':debit',
                PaymentTransaction::class,
                $transaction->id,
                'Creator share reversed after a payment refund.',
                $metadata
            );
            $this->wallets->post(
                $earning->user,
                $creatorReversal,
                'earning_reversal',
                'reversed',
                $idempotency.':record',
                PaymentTransaction::class,
                $transaction->id,
                'Recorded creator earning reversal.',
                $metadata
            );

            $snapshot['reversed_refund_minor'] = $targetRefundMinor;
            $snapshot['creator_reversed_minor'] = $targetCreatorReversal;
            $snapshot['last_refund_reference'] = $refundReference;
            $earning->update([
                'status' => $targetCreatorReversal >= $creatorTotal ? 'reversed' : $earning->status,
                'calculation_snapshot' => $snapshot,
            ]);
            $transactionMeta = $transaction->meta ?? [];
            $transactionMeta['refunded_amount_minor'] = $targetRefundMinor;
            $transactionMeta['last_refund_reference'] = $refundReference;
            $transaction->update(['meta' => $transactionMeta]);
            $this->notifications->createForUser($earning->user_id, [
                'title' => 'Creator earning adjusted',
                'message' => 'A payment refund created an auditable reversal in your creator wallet.',
                'type' => 'creator_earnings',
                'action_url' => '/creator/finance/earnings',
            ]);

            return $earning->fresh();
        });
    }
}
