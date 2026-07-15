<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FlutterwaveService;
use App\Services\PaymentApprovalService;
use App\Models\PaymentTransaction;
use App\Models\PaymentGateway;
use App\Models\Movie;
use App\Models\TVShow;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * @group Payments
 *
 * Flutterwave: initiate (returns link/redirect), verify by transaction_ref. Webhook handles callback.
 */
class FlutterwaveController extends Controller
{
    private FlutterwaveService $flutterwaveService;

    public function __construct(FlutterwaveService $flutterwaveService)
    {
        $this->flutterwaveService = $flutterwaveService;
    }

    /**
     * Initiate Flutterwave payment
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
            'subscription_plan_id' => 'required_if:type,SUBSCRIPTION|exists:subscription_plans,id',
            'return_url' => 'nullable|string|max:500',
        ]);

        $returnUrl = $this->validateReturnUrl($request->return_url);
        $meta = $returnUrl ? ['return_url' => $returnUrl] : [];

        // Get Flutterwave gateway
        $gateway = PaymentGateway::where('slug', 'flutterwave')
            ->where('is_active', true)
            ->first();

        if (!$gateway) {
            return response()->json(['error' => 'Flutterwave gateway is not available'], 400);
        }

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

        // Generate unique transaction reference
        $txRef = 'NBX-FLW-' . strtoupper(Str::random(12)) . '-' . time();

        // Create transaction record (meta.return_url for redirect after success)
        $transaction = PaymentTransaction::create([
            'user_id' => $user->id,
            'payment_gateway_id' => $gateway->id,
            'gateway_code' => $gateway->code,
            'type' => $request->type,
            'transactionable_type' => $transactionable ? get_class($transactionable) : null,
            'transactionable_id' => $transactionable ? $transactionable->id : null,
            'subscription_plan_id' => $subscriptionPlan ? $subscriptionPlan->id : null,
            'transaction_ref' => $txRef,
            'amount' => $amount,
            'status' => 'PENDING',
            'meta' => $meta,
        ]);

        // Prepare Flutterwave payload
        $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000'));
        $redirectUrl = rtrim($frontendUrl, '/') . '/payment/callback?tx_ref=' . urlencode($txRef);
        
        // Format phone number for Mobile Money Uganda (required format: 256XXXXXXXXX)
        $phoneNumber = $user->phone;
        if ($phoneNumber) {
            // Remove any spaces, dashes, or other characters
            $phoneNumber = preg_replace('/[^0-9+]/', '', $phoneNumber);
            // If starts with 0, replace with 256
            if (strpos($phoneNumber, '0') === 0) {
                $phoneNumber = '256' . substr($phoneNumber, 1);
            }
            // If starts with +256, remove the +
            if (strpos($phoneNumber, '+256') === 0) {
                $phoneNumber = substr($phoneNumber, 1);
            }
            // If doesn't start with 256, add it
            if (strpos($phoneNumber, '256') !== 0) {
                $phoneNumber = '256' . $phoneNumber;
            }
        }
        
        $paymentData = [
            'tx_ref' => $txRef,
            'amount' => $amount,
            'currency' => 'UGX',
            'redirect_url' => $redirectUrl,
            'payment_options' => 'card,mobilemoneyuganda',
            'customer' => [
                'email' => $user->email,
                'phone' => $phoneNumber,
                'name' => $user->name,
            ],
            'customizations' => [
                'title' => 'NaraBox Payment',
                'description' => $this->getPaymentDescription($request->type, $transactionable, $subscriptionPlan),
            ],
            'meta' => [
                'user_id' => $user->id,
                'payment_type' => $request->type,
                'media_id' => $request->media_id ?? null,
                'media_type' => $request->media_type ?? null,
                'subscription_plan_id' => $request->subscription_plan_id ?? null,
                'transaction_id' => $transaction->id,
            ],
        ];

        // Call Flutterwave API
        $result = $this->flutterwaveService->initiatePayment($paymentData);

        if (!$result['success']) {
            $transaction->update([
                'status' => 'FAILED',
                'gateway_response' => $result,
            ]);

            return response()->json([
                'error' => $result['message'] ?? 'Failed to initiate payment',
            ], 400);
        }

        // Store Flutterwave response
        $transaction->update([
            'gateway_transaction_id' => $result['data']['id'] ?? null,
            'external_reference' => $txRef,
            'provider_code' => 'FLUTTERWAVE',
            'gateway_response' => $result['data'],
            'raw_response' => $result['data'],
        ]);

        return response()->json([
            'success' => true,
            'transaction_ref' => $txRef,
            'checkout_url' => $result['link'],
            'public_key' => $this->flutterwaveService->getPublicKey(),
            'amount' => $amount,
            'currency' => 'UGX',
        ]);
    }

    /**
     * Verify Flutterwave payment
     */
    public function verify(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $request->validate([
            'transaction_ref' => 'required|string',
            'transaction_id' => 'nullable|string', // Flutterwave transaction ID
        ]);

        $transaction = PaymentTransaction::where('transaction_ref', $request->transaction_ref)
            ->where('user_id', $user->id)
            ->first();

        if (!$transaction) {
            return response()->json(['error' => 'Transaction not found'], 404);
        }

        // If transaction already verified, return success with redirect_url
        if ($transaction->status === 'SUCCESS') {
            return response()->json([
                'success' => true,
                'status' => 'SUCCESS',
                'message' => 'Transaction already verified',
                'redirect_url' => $this->getRedirectUrl($transaction),
                'transaction' => [
                    'ref' => $transaction->transaction_ref,
                    'type' => $transaction->type,
                ],
            ]);
        }

        // Verify with Flutterwave
        $transactionId = $request->transaction_id ?? $transaction->gateway_transaction_id;
        $verification = null;
        
        // Try transaction ID verification first
        if ($transactionId) {
            $verification = $this->flutterwaveService->verifyTransaction($transactionId);
        }
        
        // If transaction ID verification fails or not available, try tx_ref verification
        if (!$verification || !$verification['success']) {
            $verification = $this->flutterwaveService->verifyByTxRef($request->transaction_ref);
        }

        // Check gateway type - automated gateways should mark failures immediately
        $gateway = $transaction->paymentGateway;
        $isAutomatedGateway = $gateway && $gateway->type === 'AUTOMATIC';

        if (!$verification || !$verification['success']) {
            // For automated gateways, mark as FAILED immediately if verification fails
            // Only keep PENDING for manual gateways (which require admin approval)
            if ($isAutomatedGateway) {
                $transaction->update([
                    'status' => 'FAILED',
                    'failure_reason' => $verification['message'] ?? 'Payment verification failed',
                    'gateway_response' => array_merge($transaction->gateway_response ?? [], ['verification' => $verification]),
                    'raw_response' => $verification,
                ]);

                return response()->json([
                    'success' => false,
                    'status' => 'FAILED',
                    'message' => $verification['message'] ?? 'Payment verification failed',
                ], 400);
            }

            // For manual gateways, keep PENDING if still processing
            if ($transaction->status === 'PENDING') {
                return response()->json([
                    'success' => false,
                    'status' => 'PENDING',
                    'message' => 'Payment is still being processed. Please try again in a few moments.',
                ], 202); // 202 Accepted - payment still processing
            }

            $transaction->update([
                'status' => 'FAILED',
                'failure_reason' => $verification['message'] ?? 'Payment verification failed',
                'gateway_response' => array_merge($transaction->gateway_response ?? [], ['verification' => $verification]),
                'raw_response' => $verification,
            ]);

            return response()->json([
                'success' => false,
                'status' => 'FAILED',
                'message' => $verification['message'] ?? 'Payment verification failed',
            ], 400);
        }

        // Check if payment was actually successful
        if (!$verification['verified']) {
            // Check verification status for cancelled/failed states
            $verificationStatus = strtolower($verification['status'] ?? '');
            $isCancelledOrFailed = in_array($verificationStatus, ['cancelled', 'failed', 'expired', 'reversed', 'abandoned']);
            
            // For automated gateways, mark as FAILED immediately if cancelled/failed
            // Manual gateways stay PENDING for admin approval
            if ($isAutomatedGateway) {
                $message = $isCancelledOrFailed
                    ? 'Payment was cancelled or failed'
                    : 'Payment was not successful';

                $transaction->update([
                    'status' => 'FAILED',
                    'failure_reason' => $message,
                    'gateway_response' => array_merge($transaction->gateway_response ?? [], ['verification' => $verification]),
                    'raw_response' => $verification,
                ]);

                return response()->json([
                    'success' => false,
                    'status' => 'FAILED',
                    'message' => $message,
                ], 400);
            }

            // For manual gateways, only mark as FAILED if explicitly failed
            // Otherwise keep PENDING for admin review
            if ($isCancelledOrFailed) {
                $transaction->update([
                    'status' => 'FAILED',
                    'failure_reason' => 'Payment was cancelled or failed',
                    'gateway_response' => array_merge($transaction->gateway_response ?? [], ['verification' => $verification]),
                    'raw_response' => $verification,
                ]);

                return response()->json([
                    'success' => false,
                    'status' => 'FAILED',
                    'message' => 'Payment was cancelled or failed',
                ], 400);
            }

            // Payment exists but not successful - for manual gateways, keep PENDING
            // For automated gateways, this should have been caught above, but as fallback:
            if ($isAutomatedGateway) {
                $transaction->update([
                    'status' => 'FAILED',
                    'failure_reason' => 'Payment was not successful',
                    'gateway_response' => array_merge($transaction->gateway_response ?? [], ['verification' => $verification]),
                    'raw_response' => $verification,
                ]);
            }

            return response()->json([
                'success' => false,
                'status' => $isAutomatedGateway ? 'FAILED' : 'PENDING',
                'message' => $isAutomatedGateway ? 'Payment was not successful' : 'Payment is pending admin approval',
            ], $isAutomatedGateway ? 400 : 202);
        }

        // Verify amount matches
        $verifiedAmount = $verification['amount'] ?? 0;
        if (abs($verifiedAmount - $transaction->amount) > 0.01) {
            Log::warning('Flutterwave payment amount mismatch', [
                'transaction_id' => $transaction->id,
                'expected' => $transaction->amount,
                'received' => $verifiedAmount,
            ]);

            return response()->json([
                'success' => false,
                'status' => 'FAILED',
                'message' => 'Payment amount mismatch',
            ], 400);
        }

        // Update transaction
        $transaction->update([
            'status' => 'SUCCESS',
            'gateway_transaction_id' => $verification['data']['id'] ?? $transactionId,
            'gateway_response' => array_merge($transaction->gateway_response ?? [], [
                'verification' => $verification,
                'verified_at' => now()->toIso8601String(),
            ]),
            'raw_response' => $verification,
            'failure_reason' => null,
        ]);

        // Grant access
        PaymentApprovalService::grantAccess($transaction);

        $redirectUrl = $this->getRedirectUrl($transaction);

        return response()->json([
            'success' => true,
            'status' => 'SUCCESS',
            'message' => 'Payment verified successfully. Access granted.',
            'redirect_url' => $redirectUrl,
            'transaction' => [
                'ref' => $transaction->transaction_ref,
                'type' => $transaction->type,
                'amount' => $transaction->amount,
            ],
        ]);
    }

    /**
     * Webhook handler for Flutterwave
     */
    public function webhook(Request $request)
    {
        $payload = $request->all();
        
        // Verify webhook signature (if implemented by Flutterwave)
        // For now, we'll verify the transaction
        
        if (!isset($payload['data']['tx_ref'])) {
            return response()->json(['error' => 'Invalid webhook payload'], 400);
        }

        $txRef = $payload['data']['tx_ref'];
        $transaction = PaymentTransaction::where('transaction_ref', $txRef)->first();

        if (!$transaction) {
            return response()->json(['error' => 'Transaction not found'], 404);
        }

        // If already processed, return success
        if ($transaction->status === 'SUCCESS') {
            return response()->json(['status' => 'success', 'message' => 'Already processed']);
        }

        // Verify the transaction
        $verification = $this->flutterwaveService->verifyTransaction($payload['data']['id']);

        if ($verification['success'] && $verification['verified']) {
            $transaction->update([
                'status' => 'SUCCESS',
                'gateway_transaction_id' => $payload['data']['id'],
                'gateway_response' => array_merge($transaction->gateway_response ?? [], [
                    'webhook' => $payload,
                    'verification' => $verification,
                ]),
                'raw_callback' => $payload,
                'raw_response' => $verification,
                'failure_reason' => null,
            ]);

            PaymentApprovalService::grantAccess($transaction);
        } else {
            $transaction->update([
                'status' => 'FAILED',
                'gateway_response' => array_merge($transaction->gateway_response ?? [], [
                    'webhook' => $payload,
                    'verification' => $verification,
                ]),
                'raw_callback' => $payload,
                'raw_response' => $verification,
                'failure_reason' => $verification['message'] ?? 'Payment verification failed',
            ]);
        }

        return response()->json(['status' => 'success']);
    }

    private function validateReturnUrl(?string $returnUrl): ?string
    {
        if ($returnUrl === null || trim($returnUrl) === '') {
            return null;
        }
        $returnUrl = trim($returnUrl);
        if (str_starts_with($returnUrl, '/') && ! str_starts_with($returnUrl, '//')) {
            return $returnUrl;
        }
        $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000'));
        $frontendHost = parse_url($frontendUrl, PHP_URL_HOST);
        $parsed = parse_url($returnUrl);
        $host = $parsed['host'] ?? null;
        if ($host && strtolower($host) === strtolower($frontendHost)) {
            $path = $parsed['path'] ?? '/';
            $query = isset($parsed['query']) ? '?' . $parsed['query'] : '';
            return $path . $query;
        }
        return null;
    }

    private function getRedirectUrl(PaymentTransaction $transaction): ?string
    {
        $meta = $transaction->meta ?? [];
        $returnUrl = $meta['return_url'] ?? null;
        if ($returnUrl && str_starts_with($returnUrl, '/') && ! str_starts_with($returnUrl, '//')) {
            return $returnUrl;
        }
        return null;
    }

    /**
     * Get payment description
     */
    private function getPaymentDescription(string $type, $transactionable = null, $subscriptionPlan = null): string
    {
        if ($type === 'RENT' && $transactionable) {
            return 'Rent: ' . $transactionable->title;
        } elseif ($type === 'BUY' && $transactionable) {
            return 'Purchase: ' . $transactionable->title;
        } elseif ($type === 'SUBSCRIPTION' && $subscriptionPlan) {
            return 'Subscription: ' . $subscriptionPlan->name;
        }

        return 'NaraBox Payment';
    }
}

