<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserRental;
use App\Models\UserPurchase;
use App\Models\UserSubscription;
use App\Models\PaymentTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Get user's active subscription (ACTIVE status and not expired)
        // First check new user_subscriptions table
        $subscription = null;
        if (\Schema::hasTable('user_subscriptions')) {
            // Check for expired subscriptions and update them
            UserSubscription::where('user_id', $user->id)
                ->where('status', 'ACTIVE')
                ->where('expires_at', '<=', now())
                ->update(['status' => 'EXPIRED']);
            
            // Get active subscription
            $subscription = UserSubscription::where('user_id', $user->id)
                ->where('status', 'ACTIVE')
                ->where('expires_at', '>', now())
                ->with('subscriptionPlan')
                ->latest()
                ->first();
        }
        
        // Fallback to old subscriptions table if no active subscription found
        if (!$subscription) {
            $oldSubscription = \App\Models\Subscription::where('user_id', $user->id)
                ->whereRaw("UPPER(status) = 'ACTIVE'")
                ->where(function($query) {
                    $query->whereNull('end_date')
                          ->orWhere('end_date', '>', now());
                })
                ->latest()
                ->first();
            
            // If found but expired, update it
            if ($oldSubscription && $oldSubscription->end_date && $oldSubscription->end_date->isPast()) {
                $oldSubscription->update(['status' => 'EXPIRED']);
                $oldSubscription = null;
            }
        }
            
        // If no active subscription, get the latest one (even if expired) for display
        if (!$subscription) {
            $subscription = UserSubscription::where('user_id', $user->id)
                ->with('subscriptionPlan')
                ->latest()
                ->first();
        }
        
        // Update user's plan_status if subscription is expired or doesn't exist
        if (!$subscription || $subscription->status !== 'ACTIVE' || $subscription->expires_at <= now()) {
            // Check if user has any active subscription
            $hasActiveSubscription = UserSubscription::where('user_id', $user->id)
                ->where('status', 'ACTIVE')
                ->where('expires_at', '>', now())
                ->exists();
            
            if (!$hasActiveSubscription && $user->plan_status === 'ACTIVE') {
                // Update user's plan_status to NONE or EXPIRED
                $user->update([
                    'plan_status' => 'NONE',
                    'plan' => 'FREE',
                    'renewal_date' => null,
                ]);
            }
        }
            
        // Check for pending subscription payments
        $pendingSubscriptionPayment = PaymentTransaction::where('user_id', $user->id)
            ->where('type', 'SUBSCRIPTION')
            ->where('status', 'PENDING')
            ->with('subscriptionPlan')
            ->latest()
            ->first();

        // Get rentals (active and expired)
        $rentals = UserRental::where('user_id', $user->id)
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->with(['rentable', 'transaction'])
            ->get()
            ->map(function ($rental) {
                return [
                    'id' => $rental->rentable_id,
                    'media_id' => $rental->rentable_id,
                    'media_type' => $rental->rentable_type === 'App\Models\Movie' ? 'MOVIE' : 'TV_SHOW',
                    'title' => $rental->rentable->title ?? 'Unknown',
                    'thumbnail' => $rental->rentable->thumbnail ?? '',
                    'expires_at' => $rental->expires_at->toIso8601String(),
                    'rented_at' => $rental->rented_at->toIso8601String(),
                ];
            });

        // Get purchases
        $purchases = UserPurchase::where('user_id', $user->id)
            ->with(['purchasable', 'transaction'])
            ->get()
            ->map(function ($purchase) {
                return [
                    'id' => $purchase->purchasable_id,
                    'media_id' => $purchase->purchasable_id,
                    'media_type' => $purchase->purchasable_type === 'App\Models\Movie' ? 'MOVIE' : 'TV_SHOW',
                    'title' => $purchase->purchasable->title ?? 'Unknown',
                    'thumbnail' => $purchase->purchasable->thumbnail ?? '',
                    'purchased_at' => $purchase->purchased_at->toIso8601String(),
                ];
            });

        // Get all transactions
        $transactions = PaymentTransaction::where('user_id', $user->id)
            ->with(['paymentGateway', 'transactionable', 'subscriptionPlan'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($transaction) {
                $itemTitle = 'Subscription';
                if ($transaction->transactionable) {
                    $itemTitle = $transaction->transactionable->title ?? 'Unknown';
                } elseif ($transaction->subscriptionPlan) {
                    $itemTitle = $transaction->subscriptionPlan->name . ' Subscription';
                }

                return [
                    'id' => $transaction->id,
                    'transaction_ref' => $transaction->transaction_ref,
                    'type' => $transaction->type,
                    'amount' => $transaction->amount,
                    'status' => $transaction->status,
                    'payment_gateway' => [
                        'id' => $transaction->paymentGateway->id ?? null,
                        'name' => $transaction->paymentGateway->name ?? 'Unknown',
                        'display_name' => $transaction->paymentGateway->display_name ?? 'Unknown',
                    ],
                    'transactionable' => $transaction->transactionable ? [
                        'id' => $transaction->transactionable->id,
                        'title' => $transaction->transactionable->title ?? 'Unknown',
                    ] : null,
                    'itemTitle' => $itemTitle,
                    'created_at' => $transaction->created_at->toIso8601String(),
                ];
            });

        // Get watch history (if exists)
        $watchHistory = [];
        if (method_exists($user, 'watchHistory')) {
            $watchHistory = $user->watchHistory()
                ->with(['media', 'episode'])
                ->orderBy('last_watched_at', 'desc')
                ->limit(20)
                ->get()
                ->map(function ($history) {
                    return [
                        'id' => $history->media_id,
                        'title' => $history->media->title ?? 'Unknown',
                        'thumbnail' => $history->media->thumbnail ?? '',
                        'episodeId' => $history->episode_id,
                        'progressSeconds' => $history->progress_seconds,
                        'lastWatched' => $history->last_watched_at->toIso8601String(),
                    ];
                })->toArray();
        }

        // Determine plan display name and status
        $planDisplayName = 'FREE'; // Default to FREE
        $planStatus = 'NONE'; // Default to NONE
        
        if ($pendingSubscriptionPayment) {
            $planDisplayName = $pendingSubscriptionPayment->subscriptionPlan->name ?? 'FREE';
            $planStatus = 'PENDING';
        } elseif ($subscription && $subscription->status === 'ACTIVE' && $subscription->expires_at > now()) {
            $planDisplayName = $subscription->subscriptionPlan->name ?? 'PRO';
            $planStatus = 'ACTIVE';
        } else {
            // No active subscription - check if user table has outdated status
            if ($user->plan_status === 'ACTIVE') {
                $planStatus = 'NONE';
                $planDisplayName = 'FREE';
            } else {
                $planStatus = $user->plan_status ?? 'NONE';
                $planDisplayName = $user->plan ?? 'FREE';
            }
        }
        
        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'avatar' => $user->avatar,
                'plan' => $planDisplayName,
                'planStatus' => $planStatus,
                'renewalDate' => $subscription && $subscription->expires_at ? $subscription->expires_at->format('Y-m-d') : ($user->renewal_date?->format('Y-m-d')),
            ],
            'subscription' => $subscription ? [
                'plan' => $subscription->subscriptionPlan->name ?? 'Unknown',
                'status' => $subscription->status,
                'started_at' => $subscription->started_at->toIso8601String(),
                'expires_at' => $subscription->expires_at->toIso8601String(),
            ] : null,
            'pending_subscription' => $pendingSubscriptionPayment ? [
                'plan' => $pendingSubscriptionPayment->subscriptionPlan->name ?? 'Unknown',
                'status' => 'PENDING',
                'transaction_ref' => $pendingSubscriptionPayment->transaction_ref,
                'amount' => $pendingSubscriptionPayment->amount,
            ] : null,
            'vault' => [
                'rentedIds' => $rentals->pluck('id')->toArray(),
                'purchasedIds' => $purchases->pluck('id')->toArray(),
                'watchHistory' => $watchHistory,
            ],
            'rentals' => $rentals,
            'purchases' => $purchases,
            'transactions' => $transactions,
        ]);
    }
}
