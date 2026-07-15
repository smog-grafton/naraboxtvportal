<?php

namespace App\Services;

use App\Events\PaymentFailed;
use App\Models\PaymentGateway;
use App\Models\PaymentTransaction;
use Illuminate\Support\Facades\Log;

class PendingPaymentResolverService
{
    private const PENDING_TIMEOUT_MINUTES = 30;

    public function __construct(
        private readonly PawaPayService $pawaPayService,
        private readonly FlutterwaveService $flutterwaveService,
    ) {
    }

    public function resolvePendingContentTransaction(
        int $userId,
        string $transactionableType,
        int $transactionableId,
    ): ?PaymentTransaction {
        $transaction = $this->getPendingContentTransaction(
            $userId,
            $transactionableType,
            $transactionableId,
        );

        if (! $transaction) {
            return null;
        }

        $resolved = $this->resolve($transaction);

        return $resolved && $resolved->status === 'PENDING' ? $resolved : null;
    }

    public function resolvePendingSubscriptionTransaction(int $userId): ?PaymentTransaction
    {
        $transaction = $this->getPendingSubscriptionTransaction($userId);

        if (! $transaction) {
            return null;
        }

        $resolved = $this->resolve($transaction);

        return $resolved && $resolved->status === 'PENDING' ? $resolved : null;
    }

    public function getPendingContentTransaction(
        int $userId,
        string $transactionableType,
        int $transactionableId,
    ): ?PaymentTransaction {
        return PaymentTransaction::query()
            ->with('paymentGateway')
            ->where('user_id', $userId)
            ->where('transactionable_type', $transactionableType)
            ->where('transactionable_id', $transactionableId)
            ->where('status', 'PENDING')
            ->latest('id')
            ->first();
    }

    public function getPendingSubscriptionTransaction(int $userId): ?PaymentTransaction
    {
        return PaymentTransaction::query()
            ->with('paymentGateway')
            ->where('user_id', $userId)
            ->where('type', 'SUBSCRIPTION')
            ->where('status', 'PENDING')
            ->latest('id')
            ->first();
    }

    public function resolve(PaymentTransaction $transaction): PaymentTransaction
    {
        $transaction->loadMissing('paymentGateway', 'transactionable', 'subscriptionPlan', 'user');

        if ($transaction->status !== 'PENDING') {
            return $transaction;
        }

        $gateway = $transaction->paymentGateway;
        if (! $gateway || $gateway->type !== 'AUTOMATIC') {
            return $transaction;
        }

        $slug = strtolower((string) ($gateway->slug ?: $gateway->code ?: ''));

        try {
            return match ($slug) {
                'iotec' => $this->resolveIotec($transaction, $gateway),
                'pawapay' => $this->resolvePawaPay($transaction),
                'flutterwave' => $this->resolveFlutterwave($transaction),
                default => $this->expireTimedOutPending($transaction, 'Payment confirmation timed out. Please try again.'),
            };
        } catch (\Throwable $exception) {
            Log::warning('pending_payment.resolve_failed', [
                'transaction_id' => $transaction->id,
                'gateway' => $slug,
                'error' => $exception->getMessage(),
            ]);

            return $this->expireTimedOutPending(
                $transaction,
                'Payment took too long to confirm. Please try again.'
            );
        }
    }

    private function resolveIotec(PaymentTransaction $transaction, PaymentGateway $gateway): PaymentTransaction
    {
        if (! $transaction->gateway_transaction_id) {
            return $this->expireTimedOutPending(
                $transaction,
                'Payment request never reached ioTec. Please try again.'
            );
        }

        $service = new IoTeCService($gateway);
        $statusResult = $service->getStatus($transaction->gateway_transaction_id);
        $normalized = strtolower((string) ($statusResult['normalized'] ?? 'pending'));
        $message = $statusResult['error'] ?? ($statusResult['raw']['statusMessage'] ?? null);

        if ($normalized === 'success') {
            return $this->markSuccess($transaction, [
                'status_check' => $statusResult,
            ], $statusResult['raw'] ?? $statusResult);
        }

        if ($normalized === 'failed') {
            return $this->markFailed(
                $transaction,
                $message ?? 'Payment failed',
                ['status_check' => $statusResult],
                $statusResult['raw'] ?? $statusResult
            );
        }

        return $this->expireTimedOutPending(
            $transaction,
            $message ?? 'Payment confirmation timed out. Please try again.'
        );
    }

    private function resolvePawaPay(PaymentTransaction $transaction): PaymentTransaction
    {
        if (! $transaction->external_reference) {
            return $this->expireTimedOutPending(
                $transaction,
                'Payment request never reached PawaPay. Please try again.'
            );
        }

        $result = $this->pawaPayService->checkDepositStatus($transaction->external_reference);
        $normalized = strtoupper((string) ($result['normalized_status'] ?? 'PENDING'));
        $body = is_array($result['body'] ?? null) ? $result['body'] : [];
        $failureReason = $this->pawaPayService->extractFailureReason($body)
            ?? ($body['message'] ?? null);

        if ($normalized === 'SUCCESS') {
            return $this->markSuccess($transaction, [
                'status_check' => $result,
            ], $body === [] ? $result : $body);
        }

        if ($normalized === 'FAILED') {
            return $this->markFailed(
                $transaction,
                $failureReason ?? 'Payment failed',
                ['status_check' => $result],
                $body === [] ? $result : $body
            );
        }

        if (($result['ok'] ?? false) === false && $failureReason) {
            return $this->markFailed(
                $transaction,
                $failureReason,
                ['status_check' => $result],
                $body === [] ? $result : $body
            );
        }

        return $this->expireTimedOutPending(
            $transaction,
            $failureReason ?? 'Payment confirmation timed out. Please try again.'
        );
    }

    private function resolveFlutterwave(PaymentTransaction $transaction): PaymentTransaction
    {
        $verification = null;

        if ($transaction->gateway_transaction_id) {
            $verification = $this->flutterwaveService->verifyTransaction($transaction->gateway_transaction_id);
        }

        if (! $verification || ! ($verification['success'] ?? false)) {
            $verification = $this->flutterwaveService->verifyByTxRef($transaction->transaction_ref);
        }

        if (! $verification || ! ($verification['success'] ?? false)) {
            return $this->expireTimedOutPending(
                $transaction,
                $verification['message'] ?? 'Payment verification failed.'
            );
        }

        if (! ($verification['verified'] ?? false)) {
            $verificationStatus = strtolower((string) ($verification['status'] ?? ''));
            $explicitFailureStates = ['cancelled', 'failed', 'expired', 'reversed', 'abandoned'];

            if (in_array($verificationStatus, $explicitFailureStates, true)) {
                return $this->markFailed(
                    $transaction,
                    'Payment was cancelled or failed',
                    ['verification' => $verification],
                    $verification
                );
            }

            return $this->expireTimedOutPending(
                $transaction,
                'Payment was not completed.'
            );
        }

        $verifiedAmount = (float) ($verification['amount'] ?? 0);
        if (abs($verifiedAmount - (float) $transaction->amount) > 0.01) {
            return $this->markFailed(
                $transaction,
                'Payment amount mismatch',
                ['verification' => $verification],
                $verification
            );
        }

        $meta = [
            'verification' => $verification,
            'verified_at' => now()->toIso8601String(),
        ];

        if (isset($verification['data']['id'])) {
            $transaction->gateway_transaction_id = (string) $verification['data']['id'];
        }

        return $this->markSuccess($transaction, $meta, $verification);
    }

    private function markSuccess(PaymentTransaction $transaction, array $gatewayMeta, array $rawResponse): PaymentTransaction
    {
        $transaction->update([
            'status' => 'SUCCESS',
            'gateway_response' => array_merge($transaction->gateway_response ?? [], $gatewayMeta),
            'raw_response' => $rawResponse,
            'failure_reason' => null,
        ]);

        PaymentApprovalService::grantAccess($transaction->fresh(['paymentGateway', 'transactionable', 'subscriptionPlan', 'user']));

        return $transaction->fresh(['paymentGateway']);
    }

    private function markFailed(
        PaymentTransaction $transaction,
        string $reason,
        array $gatewayMeta,
        array $rawResponse
    ): PaymentTransaction {
        $transaction->update([
            'status' => 'FAILED',
            'failure_reason' => $reason,
            'gateway_response' => array_merge($transaction->gateway_response ?? [], $gatewayMeta),
            'raw_response' => $rawResponse,
        ]);

        event(new PaymentFailed($transaction->fresh(['user', 'subscriptionPlan', 'transactionable']), $reason));

        return $transaction->fresh(['paymentGateway']);
    }

    private function expireTimedOutPending(PaymentTransaction $transaction, string $fallbackReason): PaymentTransaction
    {
        if ($this->isTimedOut($transaction)) {
            return $this->markFailed(
                $transaction,
                $fallbackReason,
                ['timeout_check' => ['resolved_at' => now()->toIso8601String()]],
                $transaction->raw_response ?? $transaction->gateway_response ?? []
            );
        }

        return $transaction->fresh(['paymentGateway']) ?? $transaction;
    }

    private function isTimedOut(PaymentTransaction $transaction): bool
    {
        return (bool) $transaction->created_at?->lte(now()->subMinutes(self::PENDING_TIMEOUT_MINUTES));
    }
}
