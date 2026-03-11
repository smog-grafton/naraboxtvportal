<?php

namespace App\Console\Commands;

use App\Models\PaymentGateway;
use App\Models\PaymentTransaction;
use App\Services\PawaPayService;
use App\Services\PaymentApprovalService;
use Illuminate\Console\Command;

class PawapayReconcilePending extends Command
{
    protected $signature = 'pawapay:reconcile-pending {--minutes=5}';

    protected $description = 'Reconcile stale pending PawaPay deposits';

    public function handle(PawaPayService $pawaPayService): int
    {
        $minutes = (int) $this->option('minutes');
        $gateway = PaymentGateway::query()->where('slug', 'pawapay')->first();
        if (! $gateway) {
            $this->warn('PawaPay gateway not found.');
            return self::SUCCESS;
        }

        $cutoff = now()->subMinutes($minutes);
        $transactions = PaymentTransaction::query()
            ->where('payment_gateway_id', $gateway->id)
            ->where('status', 'PENDING')
            ->where('created_at', '<=', $cutoff)
            ->get();

        if ($transactions->isEmpty()) {
            return self::SUCCESS;
        }

        $updated = 0;
        foreach ($transactions as $transaction) {
            $depositId = $transaction->external_reference;
            if (! $depositId) {
                continue;
            }

            $result = $pawaPayService->checkDepositStatus($depositId);
            $status = $result['normalized_status'] ?? 'PENDING';
            $body = $result['body'] ?? [];
            $failureReason = $pawaPayService->extractFailureReason($body);

            if ($status === 'PENDING') {
                continue;
            }

            $transaction->update([
                'status' => $status,
                'failure_reason' => $failureReason,
                'raw_response' => $body,
                'gateway_response' => array_merge($transaction->gateway_response ?? [], ['reconcile' => $body]),
            ]);

            if ($status === 'SUCCESS') {
                PaymentApprovalService::grantAccess($transaction);
            }

            $updated++;
        }

        if ($updated > 0) {
            $this->info("Reconciled {$updated} PawaPay transaction(s).");
        }

        return self::SUCCESS;
    }
}

