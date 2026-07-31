<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Movie;
use App\Models\TVShow;
use App\Services\MediaAccessService;
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
        private readonly MediaAccessService $mediaAccess,
    ) {}

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
     * @response 200 scenario="Premium with active subscription" {
     *  "has_access": true,
     *  "access_type": "SUBSCRIPTION",
     *  "reason": "You have an active subscription",
     *  "subscription_expires_at": "2026-03-31T20:00:00+03:00"
     * }
     * @response 200 scenario="Paid but not yet rented/bought" {
     *  "has_access": false,
     *  "access_type": "PAID",
     *  "reason": "Payment required",
     *  "can_rent": true,
     *  "can_buy": true,
     *  "rent_price": 1000,
     *  "buy_price": 2200
     * }
     * @response 401 {
     *  "has_access": false,
     *  "access_type": null,
     *  "reason": "Authentication required",
     *  "requires_auth": true
     * }
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

        if (! $media) {
            return response()->json(['error' => 'Media not found'], 404);
        }

        $result = $this->mediaAccess->evaluate($media, $user);
        unset($result['http_status']);

        // This endpoint is a UI decision helper. Historically it returned 200
        // for valid locked states (LOGIN/SUBSCRIBE/RENT/BUY/PENDING), and the
        // released mobile apps depend on that contract. Returning 401/403 here
        // made Axios/React Query classify every ordinary entitlement decision
        // as a failed request and display "Sign in again". The player/download
        // endpoints remain authoritative and continue using strict HTTP status
        // codes when an attempted protected action is denied.
        return response()->json($result);
    }
}
