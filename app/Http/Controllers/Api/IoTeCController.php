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

/**
 * @group Payments
 *
 * ioTec Pay: initiate (in-site phone prompt), status (poll by transaction_ref). Webhook for callback.
 */
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
     * Initiate ioTec Pay collection (mobile money prompt, or card via hosted redirect)
     *
     * Starts a collection via ioTec Pay. Two methods are supported:
     * - `mobile_money` (default, unchanged): in-app phone prompt, no redirect.
     * - `card`: Visa/MasterCard via ioTec's PegPay-hosted form. Narabox collects only
     *   the customer's name and email; the response's `card_redirect_url` must be opened
     *   so the customer enters card details on ioTec's secure page, never in Narabox.
     *
     * Frontend should:
     * - Call this endpoint when the user chooses a payment method
     * - For `mobile_money`: poll `POST /api/v1/iotec/status` with the returned `transaction_ref`
     * - For `card`: open `card_redirect_url`, then poll the same status endpoint once the
     *   provider redirects back — a redirect alone never grants access.
     *
     * `type` and amount rules are identical to `/payments/initiate`.
     *
     * @authenticated
     *
     * @bodyParam type string required One of `RENT`, `BUY`, `SUBSCRIPTION`. Example: SUBSCRIPTION
     * @bodyParam media_id integer required_if:type,RENT,BUY The movie/TV show id for rent/buy. Example: 1
     * @bodyParam media_type string required_if:type,RENT,BUY Must be `MOVIE` or `TV_SHOW`. Example: MOVIE
     * @bodyParam subscription_plan_id integer required_if:type,SUBSCRIPTION The subscription plan id. Example: 3
     * @bodyParam method string nullable `mobile_money` (default) or `card`. Example: card
     * @bodyParam phone string required_if:method,mobile_money Uganda phone in `2567XXXXXXXX` or `07XXXXXXXX` format. Example: 256780000000
     * @bodyParam payer_name string required_if:method,card Cardholder's full name. Example: John Doe
     * @bodyParam payer_email string required_if:method,card Cardholder's email. Example: john@example.com
     * @bodyParam return_url string nullable Relative or same-origin URL to redirect to after success (eg. `/dashboard`). Example: /dashboard
     *
     * @response 200 {
     *  "transaction_ref": "NBX-IOT-ABC123XYZ0-1710240000",
     *  "payment_id": 101,
     *  "status": "PENDING",
     *  "message": "Prompt sent to 25678*****000"
     * }
     *
     * @response 400 {
     *  "error": "ioTec Pay gateway is not available"
     * }
     *
     * @response 401 {
     *  "error": "Unauthorized"
     * }
     *
     * @response 422 {
     *  "error": "Invalid Uganda phone number. Use 256XXXXXXXXX or 0XXXXXXXXX."
     * }
     */
    public function initiate(Request $request)
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $method = $request->input('method', 'mobile_money') ?: 'mobile_money';

        $request->validate([
            'media_id' => 'required_if:type,RENT,BUY|integer',
            'media_type' => 'required_if:type,RENT,BUY|in:MOVIE,TV_SHOW',
            'type' => 'required|in:RENT,BUY,SUBSCRIPTION',
            'subscription_plan_id' => 'required_if:type,SUBSCRIPTION|exists:subscription_plans,id',
            'method' => 'nullable|in:mobile_money,card',
            'phone' => 'required_if:method,mobile_money|string|max:20',
            'payer_name' => 'required_if:method,card|string|max:150',
            'payer_email' => 'required_if:method,card|email|max:255',
            'return_url' => 'nullable|string|max:500',
        ]);

        $gateway = PaymentGateway::where('slug', 'iotec')->where('is_active', true)->first();
        if (! $gateway) {
            return response()->json(['error' => 'ioTec Pay gateway is not available'], 400);
        }

        if ($method === 'card') {
            // Admin → Payment Gateways → ioTec Pay → "Card payments enabled" is the day-to-day
            // on/off switch (no deploy needed). services.iotec.card_enabled (env) is a secondary
            // hard kill-switch that stays enforced even if the DB config is misconfigured.
            $gatewayCardEnabled = (bool) ($gateway->config['card_enabled'] ?? true);
            if (! $gatewayCardEnabled || ! config('services.iotec.card_enabled', true)) {
                return response()->json(['error' => 'Card payments are not currently available'], 400);
            }
        }

        if ($method === 'mobile_money' && ! IoTeCService::validatePhone($request->phone)) {
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
        if ($method === 'card') {
            $meta['payer_email'] = $request->payer_email;
            $meta['payer_name'] = $request->payer_name;
        }

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
            'provider_code' => $method === 'card' ? 'IOTEC_CARD' : 'IOTEC',
        ]);

        $service = new IoTeCService($gateway);
        $payerNote = $request->type === 'RENT' ? 'Rent: ' . ($transactionable?->title ?? '') : ($request->type === 'BUY' ? 'Buy: ' . ($transactionable?->title ?? '') : 'Subscription: ' . ($subscriptionPlan?->name ?? ''));

        if ($method === 'card') {
            $frontendUrl = rtrim((string) config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')), '/');
            $cardRedirectTarget = $frontendUrl . '/payment/callback?tx_ref=' . urlencode($txRef) . '&provider=iotec';
            // ioTec/PegPay's hosted card form renders `payeeNote` as the "Item Description"
            // line, not `payerNote` — pass the same title + purchase-type text to both so the
            // customer sees what they're paying for instead of a generic "NaraBox".
            $result = $service->collectCard($txRef, (float) $amount, $request->payer_email, $request->payer_name, $cardRedirectTarget, $payerNote, $payerNote);
        } else {
            $result = $service->collect($txRef, (float) $amount, $request->phone, $payerNote, 'NaraBox');
        }

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

        if ($method === 'card') {
            if (empty($result['card_redirect_url'])) {
                return response()->json(['error' => 'ioTec did not return a card redirect URL'], 502);
            }
            $transaction->update([
                'meta' => array_merge($transaction->meta ?? [], ['card_redirect_url' => $result['card_redirect_url']]),
            ]);
            return response()->json([
                'transaction_ref' => $txRef,
                'payment_id' => $transaction->id,
                'status' => 'PENDING',
                'card_redirect_url' => $result['card_redirect_url'],
                'message' => 'Redirecting to secure card payment form',
            ]);
        }

        $masked = IoTeCService::maskPhone($request->phone);
        return response()->json([
            'transaction_ref' => $txRef,
            'payment_id' => $transaction->id,
            'status' => 'PENDING',
            'message' => 'Prompt sent to ' . $masked,
        ]);
    }

    /**
     * Poll ioTec Pay payment status
     *
     * Polls the current status of an ioTec payment created via `POST /iotec/initiate`.
     *
     * Normalized statuses:
     * - `success`   → payment completed, access granted, `redirect_url` provided
     * - `failed`    → permanently failed (insufficient funds, timeout, etc.)
     * - `pending`   → still waiting for user/network confirmation
     *
     * @authenticated
     *
     * @bodyParam transaction_ref string The `transaction_ref` from `/iotec/initiate`. Example: NBX-IOT-ABC123XYZ0-1710240000
     * @bodyParam payment_id integer The internal `payment_transactions.id` (alternative to transaction_ref). Example: 101
     *
     * @response 200 scenario="Success" {
     *  "status": "success",
     *  "redirect_url": "/dashboard"
     * }
     *
     * @response 200 scenario="Failed" {
     *  "status": "failed",
     *  "redirect_url": "/dashboard",
     *  "message": "Payment failed"
     * }
     *
     * @response 200 scenario="Pending" {
     *  "status": "pending",
     *  "message": "Waiting for confirmation"
     * }
     *
     * @response 401 {
     *  "error": "Unauthorized"
     * }
     *
     * @response 404 {
     *  "error": "Transaction not found"
     * }
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
     * ioTec webhook callback
     *
     * Called server-to-server by ioTec when a transaction changes status.
     * This endpoint:
     * - Locates the corresponding `payment_transactions` row
     * - Updates its status to `SUCCESS` or `FAILED`
     * - Grants access on success via `PaymentApprovalService::grantAccess()`
     *
     * You normally do **not** call this from the frontend.
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
