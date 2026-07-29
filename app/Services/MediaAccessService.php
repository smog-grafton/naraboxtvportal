<?php

namespace App\Services;

use App\Models\Episode;
use App\Models\Movie;
use App\Models\TVShow;
use App\Models\User;
use App\Models\UserPurchase;
use App\Models\UserRental;
use App\Models\UserSubscription;
use Illuminate\Database\Eloquent\Model;

class MediaAccessService
{
    public function __construct(
        private readonly PendingPaymentResolverService $pendingPaymentResolver,
    ) {}

    public function resolveMedia(Model $content): Movie|TVShow|null
    {
        if ($content instanceof Movie || $content instanceof TVShow) {
            return $content;
        }

        if (! $content instanceof Episode) {
            return null;
        }

        $content->loadMissing('season.tvShow', 'season.media');

        return $content->season?->tvShow ?? $content->season?->media;
    }

    /**
     * The single entitlement decision used by playback, access checks, and downloads.
     *
     * @return array<string, mixed>
     */
    public function evaluate(Movie|TVShow $media, ?User $user): array
    {
        $canRent = ! empty($media->price_rent);
        $canBuy = ! empty($media->price_buy);
        $pricing = [
            'can_rent' => $canRent,
            'can_buy' => $canBuy,
            'rent_price' => $media->price_rent,
            'buy_price' => $media->price_buy,
        ];

        if ((bool) $media->is_free) {
            return [
                'has_access' => true,
                'access_type' => 'FREE',
                'reason' => 'Content is free',
                'code' => 'ACCESS_GRANTED',
                'http_status' => 200,
            ];
        }

        if (! $user) {
            return array_merge([
                'has_access' => false,
                'access_type' => null,
                'reason' => 'Sign in to access this title.',
                'message' => 'Sign in again, then request the download from the title page.',
                'code' => 'AUTHENTICATION_REQUIRED',
                'requires_auth' => true,
                'http_status' => 401,
            ], $pricing);
        }

        $modelType = $media::class;

        $purchase = UserPurchase::query()
            ->where('user_id', $user->id)
            ->where('purchasable_type', $modelType)
            ->where('purchasable_id', $media->id)
            ->first();

        if ($purchase) {
            return [
                'has_access' => true,
                'access_type' => 'PURCHASED',
                'reason' => 'You own this content',
                'code' => 'ACCESS_GRANTED',
                'purchased_at' => $purchase->purchased_at?->toIso8601String(),
                'http_status' => 200,
            ];
        }

        $rental = UserRental::query()
            ->where('user_id', $user->id)
            ->where('rentable_type', $modelType)
            ->where('rentable_id', $media->id)
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->first();

        if ($rental) {
            return [
                'has_access' => true,
                'access_type' => 'RENTED',
                'reason' => 'You have rented this content',
                'code' => 'ACCESS_GRANTED',
                'expires_at' => $rental->expires_at?->toIso8601String(),
                'days_remaining' => now()->diffInDays($rental->expires_at, false),
                'http_status' => 200,
            ];
        }

        $pendingTransaction = $this->pendingPaymentResolver->getPendingContentTransaction(
            $user->id,
            $modelType,
            $media->id,
        );

        if ($pendingTransaction) {
            $isManualReview = $pendingTransaction->paymentGateway?->type === 'MANUAL';

            return array_merge([
                'has_access' => false,
                'access_type' => 'PENDING',
                'reason' => $isManualReview
                    ? 'Payment is waiting for admin approval.'
                    : 'We are still confirming your payment.',
                'code' => 'PAYMENT_PENDING',
                'pending_payment' => true,
                'transaction_ref' => $pendingTransaction->transaction_ref,
                'requires_payment' => true,
                'http_status' => 403,
            ], $pricing);
        }

        if ((bool) $media->is_premium) {
            $subscription = UserSubscription::query()
                ->where('user_id', $user->id)
                ->where('status', 'ACTIVE')
                ->where('expires_at', '>', now())
                ->latest('expires_at')
                ->first();

            if ($subscription) {
                return [
                    'has_access' => true,
                    'access_type' => 'SUBSCRIPTION',
                    'reason' => 'You have an active subscription',
                    'code' => 'ACCESS_GRANTED',
                    'subscription_expires_at' => $subscription->expires_at?->toIso8601String(),
                    'http_status' => 200,
                ];
            }

            $pendingSubscription = $this->pendingPaymentResolver
                ->getPendingSubscriptionTransaction($user->id);

            if ($pendingSubscription) {
                $isManualReview = $pendingSubscription->paymentGateway?->type === 'MANUAL';

                return array_merge([
                    'has_access' => false,
                    'access_type' => 'PENDING',
                    'reason' => $isManualReview
                        ? 'Your subscription payment is waiting for admin approval.'
                        : 'We are still confirming your subscription payment.',
                    'code' => 'PAYMENT_PENDING',
                    'pending_payment' => true,
                    'transaction_ref' => $pendingSubscription->transaction_ref,
                    'requires_subscription' => true,
                    'http_status' => 403,
                ], $pricing);
            }

            $hadExpiredSubscription = UserSubscription::query()
                ->where('user_id', $user->id)
                ->where(function ($query) {
                    $query->where('expires_at', '<=', now())
                        ->orWhereIn('status', ['EXPIRED', 'CANCELLED']);
                })
                ->exists();

            return array_merge([
                'has_access' => false,
                'access_type' => 'PREMIUM',
                'reason' => $hadExpiredSubscription
                    ? 'Your subscription has expired. Renew it to continue.'
                    : 'This title requires an active subscription.',
                'code' => $hadExpiredSubscription ? 'ENTITLEMENT_EXPIRED' : 'SUBSCRIPTION_REQUIRED',
                'requires_subscription' => true,
                'http_status' => 403,
            ], $pricing);
        }

        $hadExpiredRental = UserRental::query()
            ->where('user_id', $user->id)
            ->where('rentable_type', $modelType)
            ->where('rentable_id', $media->id)
            ->where('expires_at', '<=', now())
            ->exists();

        if ($hadExpiredRental) {
            return array_merge([
                'has_access' => false,
                'access_type' => 'PAID',
                'reason' => 'Your rental has expired. Rent or buy this title to continue.',
                'code' => 'ENTITLEMENT_EXPIRED',
                'requires_payment' => true,
                'http_status' => 403,
            ], $pricing);
        }

        $code = match (true) {
            $canRent && $canBuy => 'ENTITLEMENT_REQUIRED',
            $canRent => 'RENTAL_REQUIRED',
            $canBuy => 'PURCHASE_REQUIRED',
            default => 'ACCESS_DENIED',
        };

        return array_merge([
            'has_access' => false,
            'access_type' => $canRent || $canBuy ? 'PAID' : 'UNKNOWN',
            'reason' => $canRent || $canBuy
                ? 'Rent or buy this title to continue.'
                : 'Access to this title is unavailable.',
            'code' => $code,
            'requires_payment' => $canRent || $canBuy,
            'http_status' => 403,
        ], $pricing);
    }
}
