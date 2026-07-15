<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Movie;
use App\Models\TVShow;
use App\Models\UserRental;
use App\Models\UserPurchase;
use App\Models\UserSubscription;
use App\Services\PendingPaymentResolverService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * @group Access & Views
 *
 * Check access to content (free/subscription/rent/buy); track views.
 */
class AccessController extends Controller
{
    public function __construct(
        private readonly PendingPaymentResolverService $pendingPaymentResolver,
    ) {
    }

    /**
     * Check if user has access to a movie or TV show
     *
     * Frontend helper to decide whether to show **Play**, **Rent**, **Buy**, **Subscribe**, or a locked state.
     *
     * Rules:
     * - Free content (`is_free = 1`) is always accessible (no auth required).
     * - Premium content (`is_premium = 1`) requires an active subscription.
     * - Paid content with `price_rent` / `price_buy` checks rentals and purchases.
     * - Pending transactions are surfaced so the UI can show “Pending approval”.
     *
     * Returns a normalized `access_type`:
     * - `FREE`
     * - `SUBSCRIPTION`
     * - `PREMIUM` (subscription required, but none active)
     * - `PURCHASED`
     * - `RENTED`
     * - `PENDING`
     * - `PAID` (payment required: rent and/or buy)
     *
     * @bodyParam media_id integer required The `id` of the movie or TV show to check. Example: 1
     * @bodyParam media_type string required Must be `MOVIE` or `TV_SHOW`. Example: MOVIE
     *
     * @response 200 {
     *  "has_access": true,
     *  "access_type": "FREE",
     *  "reason": "Content is free"
     * }
     *
     * @response 200 scenario="Premium with active subscription" {
     *  "has_access": true,
     *  "access_type": "SUBSCRIPTION",
     *  "reason": "You have an active subscription",
     *  "subscription_expires_at": "2026-03-31T20:00:00+03:00"
     * }
     *
     * @response 200 scenario="Paid but not yet rented/bought" {
     *  "has_access": false,
     *  "access_type": "PAID",
     *  "reason": "Payment required",
     *  "can_rent": true,
     *  "can_buy": true,
     *  "rent_price": 1000,
     *  "buy_price": 2200
     * }
     *
     * @response 401 {
     *  "has_access": false,
     *  "access_type": null,
     *  "reason": "Authentication required",
     *  "requires_auth": true
     * }
     *
     * @response 404 {
     *  "error": "Media not found"
     * }
     */
    public function checkAccess(Request $request)
    {
        // Resolve user from Bearer token when present (route has no auth middleware)
        $user = Auth::guard('sanctum')->user();
        
        $request->validate([
            'media_id' => 'required|integer',
            'media_type' => 'required|in:MOVIE,TV_SHOW',
        ]);

        $mediaId = $request->media_id;
        $mediaType = $request->media_type;

        // Get the media item
        $media = $mediaType === 'MOVIE' 
            ? Movie::find($mediaId)
            : TVShow::find($mediaId);

        if (!$media) {
            return response()->json(['error' => 'Media not found'], 404);
        }

        // Check if content is free
        if ($media->is_free) {
            return response()->json([
                'has_access' => true,
                'access_type' => 'FREE',
                'reason' => 'Content is free',
            ]);
        }

        // If no user, check if content requires authentication
        if (!$user) {
            return response()->json([
                'has_access' => false,
                'access_type' => null,
                'reason' => 'Authentication required',
                'requires_auth' => true,
            ]);
        }

        // PRIORITY 1: Purchased access should outlive subscription expiry.
        $purchase = UserPurchase::where('user_id', $user->id)
            ->where('purchasable_type', $mediaType === 'MOVIE' ? Movie::class : TVShow::class)
            ->where('purchasable_id', $mediaId)
            ->first();

        if ($purchase) {
            return response()->json([
                'has_access' => true,
                'access_type' => 'PURCHASED',
                'reason' => 'You own this content',
                'purchased_at' => $purchase->purchased_at->toIso8601String(),
            ]);
        }

        // PRIORITY 2: Active rentals should also bypass subscription expiry.
        $rental = UserRental::where('user_id', $user->id)
            ->where('rentable_type', $mediaType === 'MOVIE' ? Movie::class : TVShow::class)
            ->where('rentable_id', $mediaId)
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->first();

        if ($rental) {
            return response()->json([
                'has_access' => true,
                'access_type' => 'RENTED',
                'reason' => 'You have rented this content',
                'expires_at' => $rental->expires_at->toIso8601String(),
                'days_remaining' => now()->diffInDays($rental->expires_at, false),
            ]);
        }

        // PRIORITY 3: Pending rent/buy payments for this title come before subscription prompts.
        $pendingTransaction = $this->pendingPaymentResolver->getPendingContentTransaction(
            $user->id,
            $mediaType === 'MOVIE' ? Movie::class : TVShow::class,
            $mediaId,
        );

        if ($pendingTransaction) {
            $isManualReview = $pendingTransaction->paymentGateway?->type === 'MANUAL';
            return response()->json([
                'has_access' => false,
                'access_type' => 'PENDING',
                'reason' => $isManualReview
                    ? 'Payment is waiting for admin approval.'
                    : 'We are still confirming your payment.',
                'pending_payment' => true,
                'transaction_ref' => $pendingTransaction->transaction_ref,
                'can_rent' => !empty($media->price_rent),
                'can_buy' => !empty($media->price_buy),
                'rent_price' => $media->price_rent,
                'buy_price' => $media->price_buy,
            ]);
        }

        // PRIORITY 4: Premium content can still be unlocked by an active subscription.
        if ($media->is_premium) {
            $activeSubscription = UserSubscription::where('user_id', $user->id)
                ->where('status', 'ACTIVE')
                ->where('expires_at', '>', now())
                ->first();

            if ($activeSubscription) {
                return response()->json([
                    'has_access' => true,
                    'access_type' => 'SUBSCRIPTION',
                    'reason' => 'You have an active subscription',
                    'subscription_expires_at' => $activeSubscription->expires_at,
                ]);
            }

            $pendingSubscription = $this->pendingPaymentResolver->getPendingSubscriptionTransaction($user->id);

            if ($pendingSubscription) {
                $isManualReview = $pendingSubscription->paymentGateway?->type === 'MANUAL';

                return response()->json([
                    'has_access' => false,
                    'access_type' => 'PENDING',
                    'reason' => $isManualReview
                        ? 'Your subscription payment is waiting for admin approval.'
                        : 'We are still confirming your subscription payment.',
                    'pending_payment' => true,
                    'transaction_ref' => $pendingSubscription->transaction_ref,
                    'requires_subscription' => true,
                ]);
            }

            return response()->json([
                'has_access' => false,
                'access_type' => 'PREMIUM',
                'reason' => 'This content requires a premium subscription. Subscribe to access premium content.',
                'requires_subscription' => true,
                'can_rent' => !empty($media->price_rent),
                'can_buy' => !empty($media->price_buy),
                'rent_price' => $media->price_rent,
                'buy_price' => $media->price_buy,
            ]);
        }

        // Content is not free or premium, so rent/buy decides access.
        return response()->json([
            'has_access' => false,
            'access_type' => 'PAID',
            'reason' => 'Payment required',
            'can_rent' => !empty($media->price_rent),
            'can_buy' => !empty($media->price_buy),
            'rent_price' => $media->price_rent,
            'buy_price' => $media->price_buy,
        ]);
    }
}
