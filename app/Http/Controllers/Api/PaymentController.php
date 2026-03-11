<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentGateway;
use App\Models\PaymentTransaction;
use App\Models\Payment;
use App\Models\Movie;
use App\Models\TVShow;
use App\Models\SubscriptionPlan;
use App\Services\PaymentApprovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    /**
     * Get all active payment gateways
     */
    public function gateways()
    {
        $gateways = PaymentGateway::where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(function ($gateway) {
                $logoUrl = $gateway->logo_path ? url(Storage::url($gateway->logo_path)) : null;

                return [
                    'id' => $gateway->id,
                    'name' => $gateway->name,
                    'slug' => $gateway->slug,
                    'code' => $gateway->code,
                    'displayName' => $gateway->display_name,
                    'display_name' => $gateway->display_name,
                    'logoPath' => $gateway->logo_path,
                    'logoUrl' => $logoUrl,
                    'helperText' => $gateway->helper_text,
                    'type' => $gateway->type,
                    'instructions' => $gateway->instructions,
                    'paymentDetails' => $gateway->payment_details,
                ];
            });

        return response()->json($gateways);
    }

    /**
     * Initiate a payment (rent, buy, or subscription)
     */
    public function initiate(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $request->validate([
            'media_id' => 'required_if:type,RENT,BUY|integer',
            'media_type' => 'required_if:type,RENT,BUY|in:MOVIE,TV_SHOW',
            'type' => 'required|in:RENT,BUY,SUBSCRIPTION',
            'gateway_id' => 'required|exists:payment_gateways,id',
            'subscription_plan_id' => 'required_if:type,SUBSCRIPTION|exists:subscription_plans,id',
            'phone' => 'nullable|string', // For mobile money gateways
        ]);

        $gateway = PaymentGateway::findOrFail($request->gateway_id);
        $amount = 0;
        $transactionable = null;
        $subscriptionPlan = null;

        // Calculate amount and get transactionable
        if ($request->type === 'RENT' || $request->type === 'BUY') {
            $media = $request->media_type === 'MOVIE'
                ? Movie::findOrFail($request->media_id)
                : TVShow::findOrFail($request->media_id);

            $transactionable = $media;
            $amount = $request->type === 'RENT' 
                ? ($media->price_rent ?? 0)
                : ($media->price_buy ?? 0);
        } elseif ($request->type === 'SUBSCRIPTION') {
            $subscriptionPlan = SubscriptionPlan::findOrFail($request->subscription_plan_id);
            $amount = $subscriptionPlan->price;
        }

        if ($amount <= 0) {
            return response()->json(['error' => 'Invalid amount'], 400);
        }

        // Create transaction
        $transaction = PaymentTransaction::create([
            'user_id' => $user->id,
            'payment_gateway_id' => $gateway->id,
            'gateway_code' => $gateway->code,
            'type' => $request->type,
            'transactionable_type' => $transactionable ? get_class($transactionable) : null,
            'transactionable_id' => $transactionable ? $transactionable->id : null,
            'subscription_plan_id' => $subscriptionPlan ? $subscriptionPlan->id : null,
            'transaction_ref' => 'NBX-' . strtoupper(Str::random(12)),
            'amount' => $amount,
            'status' => 'PENDING',
        ]);

        // If manual gateway, return instructions
        if ($gateway->type === 'MANUAL') {
            return response()->json([
                'transaction_ref' => $transaction->transaction_ref,
                'amount' => $amount,
                'status' => 'PENDING',
                'gateway_type' => 'MANUAL',
                'instructions' => $gateway->instructions,
                'payment_details' => $gateway->payment_details,
                'message' => 'Please follow the instructions and upload proof of payment',
            ]);
        }

        // For automatic gateways, process payment
        // This would integrate with actual payment gateway APIs
        return response()->json([
            'transaction_ref' => $transaction->transaction_ref,
            'amount' => $amount,
            'status' => 'PENDING',
            'gateway_type' => 'AUTOMATIC',
            'message' => 'Payment initiated. Please complete on your device.',
        ]);
    }

    /**
     * Upload payment proof for manual payments
     */
    public function uploadProof(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $request->validate([
            'transaction_ref' => 'required|exists:payment_transactions,transaction_ref',
            'proof' => 'required|file|mimes:jpeg,jpg,png,pdf|max:10240', // 10MB max
            'notes' => 'nullable|string|max:1000',
        ]);

        $transaction = PaymentTransaction::where('transaction_ref', $request->transaction_ref)
            ->where('user_id', $user->id)
            ->firstOrFail();

        if ($transaction->status !== 'PENDING') {
            return response()->json(['error' => 'Transaction is not pending'], 400);
        }

        // Store proof file
        $proofPath = $request->file('proof')->store('payment-proofs', 'public');

        // Create payment record
        $payment = Payment::create([
            'user_id' => $user->id,
            'transaction_id' => $transaction->id,
            'payment_gateway_id' => $transaction->payment_gateway_id,
            'proof_path' => $proofPath,
            'notes' => $request->notes,
            'status' => 'PENDING',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Payment proof uploaded. Waiting for admin approval.',
            'payment_id' => $payment->id,
        ]);
    }

    /**
     * Verify payment (for automatic gateways)
     */
    public function verify(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $request->validate([
            'transaction_ref' => 'required|exists:payment_transactions,transaction_ref',
        ]);

        $transaction = PaymentTransaction::where('transaction_ref', $request->transaction_ref)
            ->where('user_id', $user->id)
            ->firstOrFail();

        // For manual payments, check if approved
        if ($transaction->paymentGateway->type === 'MANUAL') {
            $payment = Payment::where('transaction_id', $transaction->id)->first();
            
            if (!$payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment proof not uploaded yet',
                ]);
            }

            if ($payment->status === 'APPROVED') {
                PaymentApprovalService::grantAccess($transaction);
                return response()->json([
                    'success' => true,
                    'status' => 'APPROVED',
                    'message' => 'Payment approved. Access granted.',
                ]);
            } elseif ($payment->status === 'REJECTED') {
                return response()->json([
                    'success' => false,
                    'status' => 'REJECTED',
                    'message' => $payment->admin_notes ?? 'Payment was rejected',
                ]);
            }

            return response()->json([
                'success' => false,
                'status' => 'PENDING',
                'message' => 'Payment is pending admin approval',
            ]);
        }

        // For automatic gateways, verify with payment provider
        // This is a placeholder - in production, integrate with actual gateway
        $transaction->update([
            'status' => 'SUCCESS',
            'gateway_transaction_id' => 'GW-' . Str::random(10),
        ]);

        PaymentApprovalService::grantAccess($transaction);

        return response()->json([
            'success' => true,
            'transaction' => [
                'ref' => $transaction->transaction_ref,
                'status' => $transaction->status,
                'type' => $transaction->type,
            ],
        ]);
    }
}
