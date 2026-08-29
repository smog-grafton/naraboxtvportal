<?php

namespace App\Services;

use App\Models\Movie;
use App\Models\PartnerAttribution;
use App\Models\PartnerBenefit;
use App\Models\PartnerBenefitClaim;
use App\Models\PartnerBenefitType;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PartnerBenefitService
{
    public function firstMovieOffer(User $user, Movie $movie): ?array
    {
        if ((bool) $movie->is_free || ! (bool) $movie->is_active) {
            return null;
        }

        $benefit = $this->activeBenefitForUser($user, PartnerBenefitType::FIRST_MOVIE_FREE);
        if (! $benefit) {
            return null;
        }

        $claim = PartnerBenefitClaim::query()
            ->where('partner_benefit_id', $benefit->id)
            ->where('user_id', $user->id)
            ->first();
        if ($claim && ($claim->status !== 'redeemed' || (int) $claim->movie_id !== (int) $movie->id)) {
            return null;
        }

        return [
            'code' => PartnerBenefitType::FIRST_MOVIE_FREE,
            'benefit_id' => $benefit->id,
            'partner_id' => $benefit->partner_id,
            'partner_name' => $benefit->partner->display_name,
            'message' => "{$benefit->partner->display_name} has offered you your first movie for FREE.",
            'cta' => $claim ? 'Watch Free' : 'Claim and Watch Free',
            'claimed' => (bool) $claim,
            'claim_id' => $claim?->id,
        ];
    }

    public function claimFirstMovie(User $user, Movie $movie): PartnerBenefitClaim
    {
        if ((bool) $movie->is_free || ! (bool) $movie->is_active) {
            throw ValidationException::withMessages(['movie_id' => ['Choose an active paid movie for this offer.']]);
        }

        $benefit = $this->activeBenefitForUser($user, PartnerBenefitType::FIRST_MOVIE_FREE);
        if (! $benefit) {
            throw ValidationException::withMessages(['movie_id' => ['This account does not have an active first-movie offer.']]);
        }

        return DB::transaction(function () use ($benefit, $user, $movie): PartnerBenefitClaim {
            $lockedBenefit = PartnerBenefit::query()->lockForUpdate()->findOrFail($benefit->id);
            if (! $lockedBenefit->enabled || ! $lockedBenefit->benefitType?->globally_enabled) {
                throw ValidationException::withMessages(['movie_id' => ['This offer is no longer available.']]);
            }

            $existing = PartnerBenefitClaim::query()
                ->where('partner_benefit_id', $lockedBenefit->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();
            if ($existing) {
                if ($existing->status === 'redeemed' && (int) $existing->movie_id === (int) $movie->id) {
                    return $existing;
                }

                throw ValidationException::withMessages(['movie_id' => ['Your first free movie offer has already been used.']]);
            }

            return PartnerBenefitClaim::query()->create([
                'partner_id' => $lockedBenefit->partner_id,
                'user_id' => $user->id,
                'partner_benefit_id' => $lockedBenefit->id,
                'movie_id' => $movie->id,
                'status' => 'redeemed',
                'claimed_at' => now(),
                'metadata' => [
                    'benefit_code' => PartnerBenefitType::FIRST_MOVIE_FREE,
                    'funding_rule' => $lockedBenefit->resolvedConfiguration()['funding_rule'] ?? 'platform_promotion_unallocated',
                    'revenue_generated_minor' => 0,
                    'partner_commission_minor' => 0,
                ],
            ]);
        });
    }

    public function applyFirstPaymentDiscount(PaymentTransaction $transaction): ?PartnerBenefitClaim
    {
        if ($transaction->status !== 'PENDING' || ! $transaction->user) {
            return null;
        }

        $benefit = $this->activeBenefitForUser($transaction->user, PartnerBenefitType::FIRST_PAYMENT_DISCOUNT);
        if (! $benefit) {
            return null;
        }

        return DB::transaction(function () use ($benefit, $transaction): ?PartnerBenefitClaim {
            $lockedTransaction = PaymentTransaction::query()->lockForUpdate()->findOrFail($transaction->id);
            $lockedBenefit = PartnerBenefit::query()->with('benefitType')->lockForUpdate()->findOrFail($benefit->id);
            if ($lockedTransaction->status !== 'PENDING'
                || ! $lockedBenefit->enabled
                || ! $lockedBenefit->benefitType?->globally_enabled) {
                return null;
            }

            $userHasPaid = PaymentTransaction::query()
                ->where('user_id', $lockedTransaction->user_id)
                ->where('id', '!=', $lockedTransaction->id)
                ->where('status', 'SUCCESS')
                ->exists();
            if ($userHasPaid) {
                return null;
            }

            $configuration = $lockedBenefit->resolvedConfiguration();
            $eligibleTypes = collect($configuration['eligible_transaction_types'] ?? ['RENT', 'BUY', 'SUBSCRIPTION'])
                ->map(fn ($type) => strtoupper(trim((string) $type)))
                ->filter()
                ->values();
            if (! $eligibleTypes->contains(strtoupper((string) $lockedTransaction->type))) {
                return null;
            }

            $originalMinor = MoneyService::toMinor((string) $lockedTransaction->amount);
            $minimumSpendMinor = max(0, (int) ($configuration['minimum_spend_minor'] ?? 0));
            $percentageBps = max(0, min(9000, (int) ($configuration['percentage_bps'] ?? 0)));
            if ($originalMinor < $minimumSpendMinor || $percentageBps === 0) {
                return null;
            }

            $discountMinor = MoneyService::share($originalMinor, $percentageBps);
            $maximumDiscountMinor = max(0, (int) ($configuration['maximum_discount_minor'] ?? 0));
            if ($maximumDiscountMinor > 0) {
                $discountMinor = min($discountMinor, $maximumDiscountMinor);
            }
            $minimumPayableMinor = max(1, (int) ($configuration['minimum_payable_minor'] ?? 500));
            $discountMinor = min($discountMinor, max(0, $originalMinor - $minimumPayableMinor));
            $actualMinor = $originalMinor - $discountMinor;
            if ($discountMinor <= 0 || $actualMinor <= 0) {
                return null;
            }

            $claim = PartnerBenefitClaim::query()
                ->where('partner_benefit_id', $lockedBenefit->id)
                ->where('user_id', $lockedTransaction->user_id)
                ->lockForUpdate()
                ->first();
            if ($claim?->status === 'redeemed') {
                return null;
            }
            if ($claim?->status === 'reserved' && (int) $claim->transaction_id !== (int) $lockedTransaction->id) {
                $prior = $claim->transaction;
                $reservationIsLive = $prior
                    && $prior->status === 'PENDING'
                    && $prior->created_at?->gte(now()->subMinutes(30));
                if ($reservationIsLive) {
                    return null;
                }
            }

            $metadata = array_merge($lockedTransaction->meta ?? [], [
                'partner_benefit_id' => $lockedBenefit->id,
                'partner_id' => $lockedBenefit->partner_id,
                'original_amount_minor' => $originalMinor,
                'discount_amount_minor' => $discountMinor,
                'actual_amount_minor' => $actualMinor,
                'discount_percentage_bps' => $percentageBps,
            ]);
            $rawRequest = $lockedTransaction->raw_request ?? [];
            if (is_array($rawRequest) && array_key_exists('amount', $rawRequest)) {
                $rawRequest['amount'] = (string) $actualMinor;
            }
            $lockedTransaction->update([
                'amount' => MoneyService::fromMinor($actualMinor),
                'meta' => $metadata,
                'raw_request' => $rawRequest ?: $lockedTransaction->raw_request,
            ]);

            $claimValues = [
                'partner_id' => $lockedBenefit->partner_id,
                'transaction_id' => $lockedTransaction->id,
                'movie_id' => $lockedTransaction->transactionable instanceof Movie
                    ? $lockedTransaction->transactionable_id
                    : null,
                'status' => 'reserved',
                'claimed_at' => null,
                'metadata' => [
                    'benefit_code' => PartnerBenefitType::FIRST_PAYMENT_DISCOUNT,
                    'transaction_type' => $lockedTransaction->type,
                    'original_amount_minor' => $originalMinor,
                    'discount_amount_minor' => $discountMinor,
                    'actual_amount_minor' => $actualMinor,
                    'percentage_bps' => $percentageBps,
                ],
            ];

            if ($claim) {
                $claim->update($claimValues);
                return $claim->fresh();
            }

            return PartnerBenefitClaim::query()->create(array_merge($claimValues, [
                'user_id' => $lockedTransaction->user_id,
                'partner_benefit_id' => $lockedBenefit->id,
            ]));
        });
    }

    public function markPaymentBenefitRedeemed(PaymentTransaction $transaction): void
    {
        PartnerBenefitClaim::query()
            ->where('transaction_id', $transaction->id)
            ->where('status', 'reserved')
            ->update(['status' => 'redeemed', 'claimed_at' => now()]);
    }

    public function activeBenefitForUser(User $user, string $code): ?PartnerBenefit
    {
        $attribution = PartnerAttribution::query()
            ->with('partner')
            ->where('user_id', $user->id)
            ->first();
        if (! $attribution?->partner || ! $attribution->partner->isActiveAt()) {
            return null;
        }

        return PartnerBenefit::query()
            ->with(['partner', 'benefitType'])
            ->where('partner_id', $attribution->partner_id)
            ->currentlyActive()
            ->whereHas('benefitType', fn ($query) => $query
                ->where('code', $code)
                ->where('globally_enabled', true))
            ->first();
    }
}
