<?php

namespace App\Services;

use App\Models\CreatorEarning;
use App\Models\CreatorPermission;
use App\Models\FinancialSetting;
use App\Models\MediaLibrary;
use App\Models\Movie;
use App\Models\Partner;
use App\Models\PartnerAttribution;
use App\Models\PartnerEarning;
use App\Models\PaymentTransaction;
use App\Models\TVShow;
use App\Models\User;
use App\Models\VJ;
use Illuminate\Support\Facades\DB;

class RevenueAllocationService
{
    public function __construct(
        private readonly PartnerAttributionService $attributions,
        private readonly CreatorWalletService $creatorWallets,
        private readonly PartnerWalletService $partnerWallets,
        private readonly UserNotificationService $notifications,
    ) {
    }

    /**
     * Calculate the only authoritative split for a successful paid transaction.
     * Amounts are integer UGX minor units; no client is allowed to recalculate this.
     *
     * @return array<string, mixed>
     */
    public function calculate(PaymentTransaction $transaction): array
    {
        $settings = FinancialSetting::current();
        $grossMinor = MoneyService::toMinor((string) $transaction->amount);
        $metadata = $transaction->meta ?? [];
        $refundMinor = max(0, (int) ($metadata['refunded_amount_minor'] ?? 0));
        $chargebackMinor = max(0, (int) ($metadata['chargeback_amount_minor'] ?? 0));
        $gatewayCostMinor = max(0, (int) ($metadata['gateway_cost_minor'] ?? 0));
        $statutoryMinor = max(0, (int) ($metadata['statutory_deductions_minor'] ?? 0));
        $commissionableMinor = max(0, $grossMinor - $refundMinor - $chargebackMinor - $gatewayCostMinor - $statutoryMinor);

        $creator = null;
        $creatorEligible = false;
        $creatorBps = 0;
        $media = null;

        if (in_array($transaction->type, ['RENT', 'BUY'], true)) {
            $media = $transaction->relationLoaded('transactionable')
                ? $transaction->transactionable
                : $transaction->transactionable;
            [$creator, $creatorEligible] = $this->resolveCreator($media);
            if ($creatorEligible) {
                $permission = CreatorPermission::query()->where('user_id', $creator->id)->first();
                $monetization = $media->monetizationSetting;
                $creatorBps = (int) (
                    $monetization?->creator_share_bps_override
                    ?? $permission?->creator_share_bps_override
                    ?? $settings?->creator_share_bps
                    ?? 7000
                );
            }
        } elseif ($transaction->type === 'SUBSCRIPTION') {
            // Subscription creator revenue is distributed by CreatorSettlementService's
            // watch-time pool. Reserve the configured pool before partner allocation.
            $creatorBps = (int) ($settings?->subscription_pool_bps ?? 0);
        }

        $creatorBps = max(0, min(10000, $creatorBps));
        $creatorMinor = in_array($transaction->type, ['RENT', 'BUY'], true) && $creatorEligible
            ? MoneyService::share($commissionableMinor, $creatorBps)
            : 0;
        $platformBps = 10000 - $creatorBps;
        $platformBeforePartnerMinor = $commissionableMinor - $creatorMinor;

        $attribution = null;
        $partner = null;
        $campaign = null;
        $partnerRateBps = 0;
        $partnerMinor = 0;

        if ($transaction->user && $transaction->created_at) {
            $attribution = $this->attributions->forTransaction($transaction->user, $transaction->created_at);
            $partner = $attribution?->partner;
            $campaign = $attribution?->campaign;
            if ($partner && $partner->isActiveAt() && $partner->isActiveAt($transaction->created_at)) {
                $partnerRateBps = $partner->rateFor((string) $transaction->type, $campaign);
                $partnerMinor = MoneyService::share($platformBeforePartnerMinor, $partnerRateBps);
            } else {
                $partner = null;
                $campaign = null;
                $attribution = null;
            }
        }

        return [
            'currency' => 'UGX',
            'transaction_id' => $transaction->id,
            'transaction_ref' => $transaction->transaction_ref,
            'transaction_type' => $transaction->type,
            'gross_minor' => $grossMinor,
            'refund_minor' => $refundMinor,
            'chargeback_minor' => $chargebackMinor,
            'gateway_cost_minor' => $gatewayCostMinor,
            'statutory_deductions_minor' => $statutoryMinor,
            'commissionable_minor' => $commissionableMinor,
            'creator' => $creator,
            'creator_eligible' => $creatorEligible,
            'creator_bps' => $creatorBps,
            'creator_minor' => $creatorMinor,
            'platform_bps_before_partner' => $platformBps,
            'platform_before_partner_minor' => $platformBeforePartnerMinor,
            'attribution' => $attribution,
            'partner' => $partner,
            'campaign' => $campaign,
            'partner_rate_bps' => $partnerRateBps,
            'partner_minor' => $partnerMinor,
            'platform_final_minor' => $platformBeforePartnerMinor - $partnerMinor,
        ];
    }

    /**
     * Idempotently post creator and partner entries for a successful transaction.
     *
     * @return array{allocation: array<string,mixed>, creator_earning: ?CreatorEarning, partner_earning: ?PartnerEarning}
     */
    public function allocateFromTransaction(PaymentTransaction $transaction): array
    {
        if ($transaction->status !== 'SUCCESS') {
            return ['allocation' => [], 'creator_earning' => null, 'partner_earning' => null];
        }

        return DB::transaction(function () use ($transaction): array {
            $allocation = $this->calculate($transaction->fresh([
                'user', 'transactionable', 'subscriptionPlan',
            ]));
            $creatorEarning = null;
            $partnerEarning = null;
            $availableAt = now()->addDays(max(0, (int) ($allocation['partner']?->payout_hold_days ?? 7)));
            $snapshot = $this->snapshot($allocation);

            if ($allocation['creator_eligible'] && in_array($transaction->type, ['RENT', 'BUY'], true)) {
                $creatorEarning = CreatorEarning::query()
                    ->where('transaction_id', $transaction->id)
                    ->lockForUpdate()
                    ->first();

                if (! $creatorEarning) {
                    $creatorAvailableAt = now()->addDays(max(0, (int) (FinancialSetting::current()?->creator_hold_days ?? 7)));
                    $creatorSnapshot = array_merge($snapshot, [
                        'partner_commission_minor' => $allocation['partner_minor'],
                        'platform_final_minor' => $allocation['platform_final_minor'],
                    ]);
                    $creatorEarning = CreatorEarning::create([
                        'user_id' => $allocation['creator']->id,
                        'transaction_id' => $transaction->id,
                        'earnable_type' => $allocation['transactionable_type'] ?? $transaction->transactionable_type,
                        'earnable_id' => $transaction->transactionable_id,
                        'gross_amount' => MoneyService::fromMinor($allocation['gross_minor']),
                        'commission_rate' => $allocation['platform_bps_before_partner'] / 100,
                        'platform_amount' => MoneyService::fromMinor($allocation['platform_before_partner_minor']),
                        'creator_amount' => MoneyService::fromMinor($allocation['creator_minor']),
                        'gross_amount_minor' => $allocation['gross_minor'],
                        'net_amount_minor' => $allocation['commissionable_minor'],
                        'creator_amount_minor' => $allocation['creator_minor'],
                        'platform_amount_minor' => $allocation['platform_before_partner_minor'],
                        'creator_share_bps' => $allocation['creator_bps'],
                        'platform_share_bps' => $allocation['platform_bps_before_partner'],
                        'eligibility_status' => 'creator_eligible',
                        'calculation_snapshot' => $creatorSnapshot,
                        'idempotency_key' => 'payment-earning:'.$transaction->transaction_ref,
                        'status' => 'pending',
                        'available_at' => $creatorAvailableAt,
                    ]);

                    if ((int) $allocation['creator_minor'] > 0) {
                        $this->creatorWallets->post(
                            $allocation['creator'],
                            (int) $allocation['creator_minor'],
                            $transaction->type === 'RENT' ? 'rent_earning' : 'purchase_earning',
                            'pending',
                            'payment-earning:'.$transaction->transaction_ref,
                            PaymentTransaction::class,
                            $transaction->id,
                            "{$transaction->type} creator earning",
                            $creatorSnapshot,
                            null,
                            $creatorAvailableAt
                        );
                    }
                }
            }

            if ($allocation['partner'] && (int) $allocation['partner_minor'] > 0) {
                $partnerEarning = PartnerEarning::query()
                    ->where('transaction_id', $transaction->id)
                    ->lockForUpdate()
                    ->first();
                if (! $partnerEarning) {
                    $partnerEarning = PartnerEarning::create([
                        'partner_id' => $allocation['partner']->id,
                        'user_id' => $allocation['partner']->user_id,
                        'transaction_id' => $transaction->id,
                        'campaign_id' => $allocation['campaign']?->id,
                        'creator_id' => $allocation['creator']?->id,
                        'gross_amount' => MoneyService::fromMinor($allocation['gross_minor']),
                        'commissionable_amount' => MoneyService::fromMinor($allocation['commissionable_minor']),
                        'creator_amount' => MoneyService::fromMinor($allocation['creator_minor']),
                        'platform_share_before_partner' => MoneyService::fromMinor($allocation['platform_before_partner_minor']),
                        'partner_rate' => $allocation['partner_rate_bps'] / 100,
                        'partner_amount' => MoneyService::fromMinor($allocation['partner_minor']),
                        'platform_final_amount' => MoneyService::fromMinor($allocation['platform_final_minor']),
                        'gross_amount_minor' => $allocation['gross_minor'],
                        'commissionable_amount_minor' => $allocation['commissionable_minor'],
                        'creator_amount_minor' => $allocation['creator_minor'],
                        'platform_share_before_partner_minor' => $allocation['platform_before_partner_minor'],
                        'partner_rate_bps' => $allocation['partner_rate_bps'],
                        'partner_amount_minor' => $allocation['partner_minor'],
                        'platform_final_amount_minor' => $allocation['platform_final_minor'],
                        'status' => 'pending',
                        'idempotency_key' => 'partner-earning:'.$transaction->transaction_ref,
                        'earned_at' => now(),
                        'available_at' => $availableAt,
                        'metadata' => array_merge($snapshot, [
                            'partner_id' => $allocation['partner']->id,
                            'attribution_id' => $allocation['attribution']?->id,
                            'campaign_id' => $allocation['campaign']?->id,
                        ]),
                    ]);
                    $this->partnerWallets->postCommission($allocation['partner']->user, $partnerEarning);
                }
            }

            return compact('allocation', 'creatorEarning', 'partnerEarning');
        });
    }

    public function reversePartnerForTransaction(PaymentTransaction $transaction, int $refundMinor, string $reference): ?PartnerEarning
    {
        $earning = PartnerEarning::query()->where('transaction_id', $transaction->id)->first();
        if (! $earning) {
            return null;
        }

        $this->partnerWallets->reverse($earning->user, $earning, $refundMinor, $reference);

        return $earning->fresh();
    }

    /** @return array<string,mixed> */
    private function snapshot(array $allocation): array
    {
        return [
            'currency' => $allocation['currency'],
            'gross_minor' => $allocation['gross_minor'],
            'refund_minor' => $allocation['refund_minor'],
            'chargeback_minor' => $allocation['chargeback_minor'],
            'gateway_cost_minor' => $allocation['gateway_cost_minor'],
            'statutory_deductions_minor' => $allocation['statutory_deductions_minor'],
            'commissionable_minor' => $allocation['commissionable_minor'],
            'creator_share_bps' => $allocation['creator_bps'],
            'creator_amount_minor' => $allocation['creator_minor'],
            'platform_share_before_partner_minor' => $allocation['platform_before_partner_minor'],
            'partner_rate_bps' => $allocation['partner_rate_bps'],
            'partner_amount_minor' => $allocation['partner_minor'],
            'platform_final_minor' => $allocation['platform_final_minor'],
            'rate_source' => $allocation['campaign'] ? 'campaign' : 'partner_default_or_transaction_override',
        ];
    }

    /** @return array{0: User|null, 1: bool} */
    private function resolveCreator(mixed $media): array
    {
        if (! $media instanceof Movie && ! $media instanceof TVShow) {
            return [null, false];
        }

        $profile = null;
        if ($media->media_library_id) {
            $profile = $media->mediaLibrary ?? MediaLibrary::find($media->media_library_id);
        } elseif ($media->vj_id) {
            $profile = $media->vj ?? VJ::find($media->vj_id);
        }

        if (! $profile?->user_id) {
            return [null, false];
        }

        $permission = CreatorPermission::query()->where('user_id', $profile->user_id)->first();
        $eligible = (bool) ($profile->is_verified ?? false)
            && $permission?->identity_verified
            && $permission?->monetization_enabled
            && ! $permission?->is_suspended
            && ! $permission?->is_revoked;

        return [$profile->user, $eligible];
    }
}
