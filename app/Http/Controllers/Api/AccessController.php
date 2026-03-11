<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Movie;
use App\Models\TVShow;
use App\Models\UserRental;
use App\Models\UserPurchase;
use App\Models\UserSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AccessController extends Controller
{
    /**
     * Check if user has access to a movie or TV show
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

        // PRIORITY 1: For premium content, check subscription FIRST (subscription overrides everything)
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

            // Premium content without subscription - subscription is still required
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

        // PRIORITY 2: Check if user has purchased (permanent access)
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

        // PRIORITY 3: Check if user has active rental
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

        // PRIORITY 4: Check for pending payments
        $pendingTransaction = \App\Models\PaymentTransaction::where('user_id', $user->id)
            ->where('transactionable_type', $mediaType === 'MOVIE' ? Movie::class : TVShow::class)
            ->where('transactionable_id', $mediaId)
            ->where('status', 'PENDING')
            ->first();

        if ($pendingTransaction) {
            return response()->json([
                'has_access' => false,
                'access_type' => 'PENDING',
                'reason' => 'Payment pending approval',
                'pending_payment' => true,
                'transaction_ref' => $pendingTransaction->transaction_ref,
                'can_rent' => !empty($media->price_rent),
                'can_buy' => !empty($media->price_buy),
                'rent_price' => $media->price_rent,
                'buy_price' => $media->price_buy,
            ]);
        }

        // Content is not free, not premium, check if rent/buy available
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
