<?php

namespace App\Console\Commands;

use App\Models\PaymentGateway;
use App\Models\PaymentTransaction;
use App\Services\IoTeCService;
use App\Services\PaymentApprovalService;
use Illuminate\Console\Command;

class IotecReconcilePending extends Command
{
    protected $signature = 'iotec:reconcile-pending {--minutes=10}';

    protected $description = 'Reconcile pending ioTec Pay transactions (check status and finalize)';

    public function handle(): int
    {
        $minutes = (int) $this->option('minutes');
        $gateway = PaymentGateway::where('slug', 'iotec')->first();
        if (! $gateway) {
            $this->warn('ioTec gateway not found.');
            return self::SUCCESS;
        }

        $cutoff = now()->subMinutes($minutes);
        $transactions = PaymentTransaction::where('payment_gateway_id', $gateway->id)
            ->where('status', 'PENDING')
            ->where('created_at', '<', $cutoff)
            ->get();

        if ($transactions->isEmpty()) {
            return self::SUCCESS;
        }

        $service = new IoTeCService($gateway);
        $updated = 0;

        foreach ($transactions as $transaction) {
            if (! $transaction->gateway_transaction_id) {
                continue;
            }
            $result = $service->getStatus($transaction->gateway_transaction_id);
            $normalized = $result['normalized'] ?? 'pending';

            if ($normalized === 'success') {
                $transaction->update([
                    'status' => 'SUCCESS',
                    'gateway_response' => array_merge($transaction->gateway_response ?? [], ['reconcile' => $result]),
                ]);
                PaymentApprovalService::grantAccess($transaction);
                $updated++;
            } elseif ($normalized === 'failed') {
                $transaction->update([
                    'status' => 'FAILED',
                    'gateway_response' => array_merge($transaction->gateway_response ?? [], ['reconcile' => $result]),
                ]);
                $updated++;
            }
        }

        if ($updated > 0) {
            $this->info("Reconciled {$updated} ioTec transaction(s).");
        }

        return self::SUCCESS;
    }
}
