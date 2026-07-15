<?php

namespace App\Services;

use App\Events\PaymentSucceeded;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\UserRental;
use App\Models\UserPurchase;
use App\Models\UserSubscription;

class PaymentApprovalService
{
    /**
     * Grant access when a transaction is approved (subscription, rent, buy).
     * Updates user, creates UserSubscription/UserRental/UserPurchase.
     */
    public static function grantAccess(PaymentTransaction $transaction): void
    {
        static::grantAccessForTransaction($transaction, false);
    }

    private static function grantAccessForTransaction(PaymentTransaction $transaction, bool $skipTransactionUpdate = false): void
    {
        $user = $transaction->user;

        if ($transaction->type === 'RENT' && $transaction->transactionable) {
            $existingRental = UserRental::where('user_id', $user->id)
                ->where('rentable_type', get_class($transaction->transactionable))
                ->where('rentable_id', $transaction->transactionable->id)
                ->where('is_active', true)
                ->first();

            if ($existingRental) {
                $existingRental->update(['expires_at' => now()->addDays(30)]);
            } else {
                UserRental::create([
                    'user_id' => $user->id,
                    'rentable_type' => get_class($transaction->transactionable),
                    'rentable_id' => $transaction->transactionable->id,
                    'transaction_id' => $transaction->id,
                    'rented_at' => now(),
                    'expires_at' => now()->addDays(30),
                    'is_active' => true,
                ]);
            }
        } elseif ($transaction->type === 'BUY' && $transaction->transactionable) {
            $existingPurchase = UserPurchase::where('user_id', $user->id)
                ->where('purchasable_type', get_class($transaction->transactionable))
                ->where('purchasable_id', $transaction->transactionable->id)
                ->first();

            if (!$existingPurchase) {
                UserPurchase::create([
                    'user_id' => $user->id,
                    'purchasable_type' => get_class($transaction->transactionable),
                    'purchasable_id' => $transaction->transactionable->id,
                    'transaction_id' => $transaction->id,
                    'purchased_at' => now(),
                ]);
            }
        } elseif ($transaction->type === 'SUBSCRIPTION' && $transaction->subscriptionPlan) {
            $subscription = UserSubscription::where('user_id', $user->id)
                ->where('status', 'ACTIVE')
                ->first();

            $startDate = $subscription && $subscription->expires_at > now()
                ? $subscription->expires_at
                : now();

            if ($subscription) {
                $subscription->update([
                    'subscription_plan_id' => $transaction->subscription_plan_id,
                    'transaction_id' => $transaction->id,
                    'started_at' => $startDate,
                    'expires_at' => $startDate->copy()->addDays($transaction->subscriptionPlan->duration_days),
                    'status' => 'ACTIVE',
                ]);
            } else {
                UserSubscription::create([
                    'user_id' => $user->id,
                    'subscription_plan_id' => $transaction->subscription_plan_id,
                    'transaction_id' => $transaction->id,
                    'started_at' => $startDate,
                    'expires_at' => $startDate->copy()->addDays($transaction->subscriptionPlan->duration_days),
                    'status' => 'ACTIVE',
                ]);
            }

            // users.plan is now varchar - store subscription plan name
            $user->update([
                'plan' => $transaction->subscriptionPlan->name,
                'plan_status' => 'ACTIVE',
                'renewal_date' => $startDate->copy()->addDays($transaction->subscriptionPlan->duration_days),
            ]);
        }

        if (!$skipTransactionUpdate) {
            $transaction->update(['status' => 'SUCCESS']);
        }

        if (in_array($transaction->type, ['RENT', 'BUY'])) {
            app(CreatorEarningsService::class)->allocateFromTransaction($transaction);
        }

        event(new PaymentSucceeded($transaction->fresh(['user', 'subscriptionPlan', 'transactionable', 'paymentGateway'])));
    }

    /**
     * When Payment (manual proof) is approved: sync transaction, grant access.
     * Also runs grant logic if transaction was already SUCCESS but subscription wasn't created (retroactive fix).
     */
    public static function approvePayment(Payment $payment): void
    {
        $transaction = $payment->transaction;
        if (!$transaction) {
            return;
        }

        // Always run grant logic - handles both new approvals and retroactive (transaction SUCCESS but no subscription)
        static::grantAccessForTransaction($transaction, $transaction->status === 'SUCCESS');
        $payment->update([
            'status' => 'APPROVED',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);
    }

    /**
     * When PaymentTransaction status set to SUCCESS: sync Payment, grant access.
     */
    public static function approveTransaction(PaymentTransaction $transaction): void
    {
        $payment = $transaction->payment;
        if ($payment && $payment->status !== 'APPROVED') {
            $payment->update([
                'status' => 'APPROVED',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);
        }

        static::grantAccessForTransaction($transaction, $transaction->status === 'SUCCESS');
    }
}
