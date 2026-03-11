<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Movie;
use App\Models\PaymentGateway;
use App\Models\PaymentTransaction;
use App\Models\SubscriptionPlan;
use App\Models\TVShow;
use App\Services\IoTeCService;
use App\Services\PaymentApprovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class IoTeCController extends Controller
{
    /**
     * Validate return_url: same-origin only (relative path or same host as frontend).
     */
    private function validateReturnUrl(?string $returnUrl): ?string
    {
        if ($returnUrl === null || $returnUrl === '') {
            return null;
        }
        $returnUrl = trim($returnUrl);
        if ($returnUrl === '') {
            return null;
        }
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

    /**
     * Initiate ioTec Pay collection (phone prompt, in-site).
     */
    public function initiate(Request $request)
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $request->validate([
            'media_id' => 'required_if:type,RENT,BUY|integer',
            'media_type' => 'required_if:type,RENT,BUY|in:MOVIE,TV_SHOW',
            'type' => 'required|in:RENT,BUY,SUBSCRIPTION',
            'subscription_plan_id' => 'required_if:type,SUBSCRIPTION|exists:subscription_plans,id',
            'phone' => 'required|string|max:20',
            'return_url' => 'nullable|string|max:500',
        ]);

        $gateway = PaymentGateway::where('slug', 'iotec')->where('is_active', true)->first();
        if (! $gateway) {
            return response()->json(['error' => 'ioTec Pay gateway is not available'], 400);
        }

        if (! IoTeCService::validatePhone($request->phone)) {
            return response()->json(['error' => 'Invalid Uganda phone number. Use 256XXXXXXXXX or 0XXXXXXXXX.'], 422);
        }

        $amount = 0;
        $transactionable = null;
        $subscriptionPlan = null;

        if ($request->type === 'RENT' || $request->type === 'BUY') {
            $media = $request->media_type === 'MOVIE'
                ? Movie::findOrFail($request->media_id)
                : TVShow::findOrFail($request->media_id);
            $transactionable = $media;
            $amount = $request->type === 'RENT' ? ($media->price_rent ?? 0) : ($media->price_buy ?? 0);
        } elseif ($request->type === 'SUBSCRIPTION') {
            $subscriptionPlan = SubscriptionPlan::findOrFail($request->subscription_plan_id);
            $amount = $subscriptionPlan->price;
        }

        if ($amount < 500) {
            return response()->json(['error' => 'Amount must be at least 500 UGX'], 400);
        }

        $returnUrl = $this->validateReturnUrl($request->return_url);
        $meta = $returnUrl ? ['return_url' => $returnUrl] : [];

        $txRef = 'NBX-IOT-' . strtoupper(Str::random(10)) . '-' . time();

        $transaction = PaymentTransaction::create([
            'user_id' => $user->id,
            'payment_gateway_id' => $gateway->id,
            'gateway_code' => $gateway->code,
            'type' => $request->type,
            'transactionable_type' => $transactionable ? get_class($transactionable) : null,
            'transactionable_id' => $transactionable ? $transactionable->id : null,
            'subscription_plan_id' => $subscriptionPlan?->id,
            'transaction_ref' => $txRef,
            'amount' => $amount,
            'status' => 'PENDING',
            'meta' => $meta,
            'provider_code' => 'IOTEC',
        ]);

        $service = new IoTeCService($gateway);
        $payerNote = $request->type === 'RENT' ? 'Rent: ' . ($transactionable?->title ?? '') : ($request->type === 'BUY' ? 'Buy: ' . ($transactionable?->title ?? '') : 'Subscription: ' . ($subscriptionPlan?->name ?? ''));
        $result = $service->collect($txRef, (float) $amount, $request->phone, $payerNote, 'NaraBox');

        if (isset($result['error'])) {
            $transaction->update([
                'status' => 'FAILED',
                'failure_reason' => $result['error'],
                'gateway_response' => $result,
                'raw_response' => $result,
            ]);
            return response()->json(['error' => $result['error']], 400);
        }

        $transaction->update([
            'gateway_transaction_id' => $result['request_id'],
            'external_reference' => $result['request_id'],
            'gateway_response' => $result['raw'] ?? $result,
            'raw_response' => $result['raw'] ?? $result,
        ]);

        $masked = IoTeCService::maskPhone($request->phone);
        return response()->json([
            'transaction_ref' => $txRef,
            'payment_id' => $transaction->id,
            'status' => 'PENDING',
            'message' => 'Prompt sent to ' . $masked,
        ]);
    }

    /**
     * Poll payment status. Returns status and redirect_url on success.
     */
    public function status(Request $request)
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $request->validate([
            'transaction_ref' => 'nullable|string',
            'payment_id' => 'nullable|integer',
        ]);

        if (! $request->transaction_ref && ! $request->payment_id) {
            return response()->json(['error' => 'Provide transaction_ref or payment_id'], 422);
        }

        $query = PaymentTransaction::where('user_id', $user->id)->where('payment_gateway_id', PaymentGateway::where('slug', 'iotec')->value('id'));
        if ($request->transaction_ref) {
            $query->where('transaction_ref', $request->transaction_ref);
        } else {
            $query->where('id', $request->payment_id);
        }
        $transaction = $query->first();
        if (! $transaction) {
            return response()->json(['error' => 'Transaction not found'], 404);
        }

        $redirectUrl = $this->getRedirectUrl($transaction);

        if (in_array($transaction->status, ['SUCCESS', 'FAILED', 'CANCELLED'], true)) {
            $normalized = strtolower($transaction->status);
            if ($normalized === 'success') {
                return response()->json(['status' => 'success', 'redirect_url' => $redirectUrl]);
            }
            return response()->json([
                'status' => $normalized,
                'redirect_url' => $redirectUrl,
                'message' => $transaction->status === 'FAILED' ? 'Payment failed' : 'Payment cancelled',
            ]);
        }

        $gateway = $transaction->paymentGateway;
        $service = new IoTeCService($gateway);
        $statusResult = $service->getStatus($transaction->gateway_transaction_id);
        $normalized = $statusResult['normalized'] ?? 'pending';

        if ($normalized === 'success') {
            $transaction->update([
                'status' => 'SUCCESS',
                'gateway_response' => array_merge($transaction->gateway_response ?? [], ['status_check' => $statusResult]),
                'raw_response' => $statusResult['raw'] ?? $statusResult,
            ]);
            PaymentApprovalService::grantAccess($transaction);
            return response()->json(['status' => 'success', 'redirect_url' => $redirectUrl]);
        }

        if ($normalized === 'failed') {
            $transaction->update([
                'status' => 'FAILED',
                'gateway_response' => array_merge($transaction->gateway_response ?? [], ['status_check' => $statusResult]),
                'raw_response' => $statusResult['raw'] ?? $statusResult,
                'failure_reason' => $statusResult['error'] ?? $statusResult['raw']['statusMessage'] ?? null,
            ]);
            return response()->json([
                'status' => 'failed',
                'message' => $statusResult['error'] ?? $statusResult['raw']['statusMessage'] ?? 'Payment failed',
            ]);
        }

        return response()->json([
            'status' => 'pending',
            'message' => 'Waiting for confirmation',
        ]);
    }

    /**
     * Webhook for ioTec callbacks (configure callback URL in ioTec wallet settings).
     */
    public function webhook(Request $request)
    {
        $payload = $request->all();
        $requestId = $payload['id'] ?? null;
        $externalId = $payload['externalId'] ?? null;
        $status = $payload['status'] ?? null;

        if (! $requestId && ! $externalId) {
            return response()->json(['error' => 'Invalid payload'], 400);
        }

        $transaction = null;
        if ($requestId) {
            $transaction = PaymentTransaction::where('gateway_transaction_id', $requestId)->first();
        }
        if (! $transaction && $externalId) {
            $transaction = PaymentTransaction::where('transaction_ref', $externalId)->first();
        }
        if (! $transaction) {
            return response()->json(['error' => 'Transaction not found'], 404);
        }

        if ($transaction->status === 'SUCCESS') {
            return response()->json(['status' => 'ok', 'message' => 'Already processed']);
        }

        if (strtolower((string) $status) === 'success') {
            $transaction->update([
                'status' => 'SUCCESS',
                'gateway_response' => array_merge($transaction->gateway_response ?? [], ['webhook' => $payload]),
                'raw_callback' => $payload,
            ]);
            PaymentApprovalService::grantAccess($transaction);
        } elseif (in_array(strtolower((string) $status), ['failed', 'cancelled', 'rejected'], true)) {
            $transaction->update([
                'status' => 'FAILED',
                'gateway_response' => array_merge($transaction->gateway_response ?? [], ['webhook' => $payload]),
                'raw_callback' => $payload,
                'failure_reason' => $payload['reason'] ?? $payload['statusMessage'] ?? 'Payment failed',
            ]);
        }

        return response()->json(['status' => 'ok']);
    }

    private function getRedirectUrl(PaymentTransaction $transaction): string
    {
        $meta = $transaction->meta ?? [];
        $returnUrl = $meta['return_url'] ?? null;
        if ($returnUrl && str_starts_with($returnUrl, '/') && ! str_starts_with($returnUrl, '//')) {
            return $returnUrl;
        }
        return '/dashboard';
    }
}
