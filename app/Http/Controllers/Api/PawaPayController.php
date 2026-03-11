<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Movie;
use App\Models\PaymentGateway;
use App\Models\PaymentTransaction;
use App\Models\SubscriptionPlan;
use App\Models\TVShow;
use App\Services\PawaPayService;
use App\Services\PaymentApprovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PawaPayController extends Controller
{
    public function initiateDeposit(Request $request, PawaPayService $pawaPayService)
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'media_id' => 'required_if:type,RENT,BUY|integer',
            'media_type' => 'required_if:type,RENT,BUY|in:MOVIE,TV_SHOW',
            'type' => 'required|in:RENT,BUY,SUBSCRIPTION',
            'subscription_plan_id' => 'required_if:type,SUBSCRIPTION|exists:subscription_plans,id',
            'phone' => 'required|string|max:20',
            'provider' => 'required|string|in:MTN_MOMO_UGA,AIRTEL_OAPI_UGA',
            'currency' => 'required|string|size:3',
            'deposit_id' => 'nullable|uuid',
            'client_reference_id' => 'nullable|string|max:255',
        ]);

        $gateway = PaymentGateway::query()
            ->where('slug', 'pawapay')
            ->where('is_active', true)
            ->first();

        if (! $gateway) {
            return response()->json(['error' => 'PawaPay gateway is not available'], 400);
        }

        $amount = $this->resolveAmount($validated);
        if ($amount <= 0) {
            return response()->json(['error' => 'Invalid amount'], 400);
        }

        $depositId = $validated['deposit_id'] ?? (string) Str::uuid();
        $phone = $this->normalizeUgandaMsisdn((string) $validated['phone']);
        if (! $phone) {
            return response()->json([
                'success' => false,
                'status' => 'FAILED',
                'message' => 'Invalid phone number. Use 07XXXXXXXX, 7XXXXXXXX, +2567XXXXXXXX or 2567XXXXXXXX.',
            ], 422);
        }

        $existing = PaymentTransaction::query()
            ->where('payment_gateway_id', $gateway->id)
            ->where('external_reference', $depositId)
            ->first();

        if ($existing) {
            return response()->json([
                'success' => true,
                'transaction_id' => $existing->id,
                'deposit_id' => $existing->external_reference,
                'transaction_ref' => $existing->transaction_ref,
                'status' => $this->toFrontendStatus($existing->status),
                'message' => 'Existing deposit request found',
            ]);
        }

        [$transactionableType, $transactionableId, $subscriptionPlanId] = $this->resolveReferences($validated);

        $transaction = PaymentTransaction::create([
            'user_id' => $user->id,
            'payment_gateway_id' => $gateway->id,
            'gateway_code' => $gateway->code,
            'type' => $validated['type'],
            'transactionable_type' => $transactionableType,
            'transactionable_id' => $transactionableId,
            'subscription_plan_id' => $subscriptionPlanId,
            'transaction_ref' => 'NBX-PWP-' . strtoupper(Str::random(10)) . '-' . time(),
            'amount' => $amount,
            'status' => 'PENDING',
            'external_reference' => $depositId,
            'provider_code' => $validated['provider'],
            'raw_request' => [
                'depositId' => $depositId,
                'phone' => $phone,
                'provider' => $validated['provider'],
                'amount' => (string) $amount,
                'currency' => strtoupper($validated['currency']),
                'clientReferenceId' => $validated['client_reference_id'] ?? null,
            ],
        ]);

        Log::info('pawapay.deposit.initiate.requested', [
            'deposit_id' => $depositId,
            'transaction_id' => $transaction->id,
        ]);

        $result = $pawaPayService->initiateDeposit(
            $depositId,
            $phone,
            $validated['provider'],
            (string) $amount,
            strtoupper($validated['currency']),
            $validated['client_reference_id'] ?? $transaction->transaction_ref,
            [['transactionRef' => $transaction->transaction_ref]]
        );

        $status = $result['normalized_status'];
        $rawBody = $result['body'] ?? [];
        $failureReason = $pawaPayService->extractFailureReason($rawBody);

        $transaction->update([
            'status' => $status,
            'failure_reason' => $failureReason,
            'raw_response' => $rawBody,
            'gateway_response' => $rawBody,
        ]);

        if ($status === 'FAILED') {
            return response()->json([
                'success' => false,
                'transaction_id' => $transaction->id,
                'deposit_id' => $depositId,
                'transaction_ref' => $transaction->transaction_ref,
                'status' => 'FAILED',
                'message' => $failureReason ?? 'Deposit initiation failed',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'transaction_id' => $transaction->id,
            'deposit_id' => $depositId,
            'transaction_ref' => $transaction->transaction_ref,
            'status' => $this->toFrontendStatus($transaction->status),
            'message' => 'Deposit initiated. Check your phone to approve payment.',
        ]);
    }

    public function checkDepositStatus(Request $request, string $depositId, PawaPayService $pawaPayService)
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $transaction = PaymentTransaction::query()
            ->where('user_id', $user->id)
            ->where('external_reference', $depositId)
            ->first();

        if (! $transaction) {
            return response()->json(['error' => 'Transaction not found'], 404);
        }

        if (in_array($transaction->status, ['SUCCESS', 'FAILED', 'CANCELLED'], true)) {
            return response()->json([
                'success' => true,
                'transaction_id' => $transaction->id,
                'deposit_id' => $depositId,
                'status' => $this->toFrontendStatus($transaction->status),
                'message' => $transaction->failure_reason,
            ]);
        }

        $result = $pawaPayService->checkDepositStatus($depositId);
        $this->applyProviderResult($transaction, $result, $pawaPayService);

        return response()->json([
            'success' => true,
            'transaction_id' => $transaction->id,
            'deposit_id' => $depositId,
            'status' => $this->toFrontendStatus($transaction->status),
            'message' => $transaction->failure_reason,
        ]);
    }

    public function depositWebhook(Request $request, PawaPayService $pawaPayService)
    {
        if (! $this->verifyCallbackSignature($request)) {
            return response()->json(['error' => 'Invalid callback signature'], 401);
        }

        $payload = $request->all();
        $depositId = $payload['depositId'] ?? null;

        if (! is_string($depositId) || $depositId === '') {
            return response()->json(['error' => 'Missing depositId'], 400);
        }

        $transaction = PaymentTransaction::query()
            ->where('external_reference', $depositId)
            ->first();

        if (! $transaction) {
            return response()->json(['status' => 'ok']);
        }

        $transaction->update([
            'raw_callback' => $payload,
        ]);

        Log::info('pawapay.deposit.webhook.received', [
            'deposit_id' => $depositId,
            'transaction_id' => $transaction->id,
        ]);

        // Server-to-server check remains source of truth.
        $result = $pawaPayService->checkDepositStatus($depositId);
        $this->applyProviderResult($transaction, $result, $pawaPayService);

        return response()->json(['status' => 'ok']);
    }

    public function refundWebhook()
    {
        return response()->json([
            'status' => 'disabled',
            'message' => 'Refund webhooks are not enabled yet.',
        ], 202);
    }

    private function applyProviderResult(PaymentTransaction $transaction, array $result, PawaPayService $pawaPayService): void
    {
        $body = $result['body'] ?? [];
        $normalized = $result['normalized_status'] ?? 'PENDING';
        $failureReason = $pawaPayService->extractFailureReason($body);

        $transaction->update([
            'status' => $normalized,
            'failure_reason' => $failureReason,
            'raw_response' => $body,
            'gateway_response' => array_merge($transaction->gateway_response ?? [], ['pawapay_status' => $body]),
        ]);

        if ($normalized === 'SUCCESS') {
            PaymentApprovalService::grantAccess($transaction);
        }
    }

    private function resolveAmount(array $validated): float
    {
        if ($validated['type'] === 'SUBSCRIPTION') {
            $plan = SubscriptionPlan::findOrFail($validated['subscription_plan_id']);
            return (float) $plan->price;
        }

        if ($validated['type'] === 'RENT' || $validated['type'] === 'BUY') {
            $media = $validated['media_type'] === 'MOVIE'
                ? Movie::findOrFail($validated['media_id'])
                : TVShow::findOrFail($validated['media_id']);

            return (float) ($validated['type'] === 'RENT' ? ($media->price_rent ?? 0) : ($media->price_buy ?? 0));
        }

        return 0.0;
    }

    private function resolveReferences(array $validated): array
    {
        if ($validated['type'] === 'SUBSCRIPTION') {
            return [null, null, (int) $validated['subscription_plan_id']];
        }

        $media = $validated['media_type'] === 'MOVIE'
            ? Movie::findOrFail($validated['media_id'])
            : TVShow::findOrFail($validated['media_id']);

        return [get_class($media), $media->id, null];
    }

    private function toFrontendStatus(string $status): string
    {
        return match ($status) {
            'SUCCESS' => 'COMPLETED',
            'FAILED' => 'FAILED',
            'CANCELLED' => 'FAILED',
            default => 'PENDING',
        };
    }

    /**
     * Normalize Uganda mobile number to pawaPay MSISDN format: 2567XXXXXXXX.
     */
    private function normalizeUgandaMsisdn(string $input): ?string
    {
        $digits = preg_replace('/\D+/', '', $input);
        if (! is_string($digits) || $digits === '') {
            return null;
        }

        if (str_starts_with($digits, '256') && strlen($digits) === 12) {
            $normalized = $digits;
        } elseif (str_starts_with($digits, '0') && strlen($digits) === 10) {
            $normalized = '256' . substr($digits, 1);
        } elseif (str_starts_with($digits, '7') && strlen($digits) === 9) {
            $normalized = '256' . $digits;
        } else {
            return null;
        }

        return preg_match('/^2567\d{8}$/', $normalized) === 1 ? $normalized : null;
    }

    private function verifyCallbackSignature(Request $request): bool
    {
        if (! config('services.pawapay.verify_callback_signature', false)) {
            return true;
        }

        // Phase-2 hardening can replace this with full RFC-9421 validation.
        return $request->headers->has('Signature')
            && $request->headers->has('Signature-Input')
            && $request->headers->has('Content-Digest');
    }
}

