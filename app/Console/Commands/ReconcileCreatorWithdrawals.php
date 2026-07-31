<?php

namespace App\Console\Commands;

use App\Models\CreatorWithdrawalRequest;
use App\Services\WithdrawalService;
use Illuminate\Console\Command;

class ReconcileCreatorWithdrawals extends Command
{
    protected $signature = 'creator:reconcile-withdrawals {--limit=100}';

    protected $description = 'Reconcile processing ioTec creator withdrawals without treating request acceptance as payment';

    public function handle(WithdrawalService $withdrawals): int
    {
        $items = CreatorWithdrawalRequest::query()
            ->where('status', CreatorWithdrawalRequest::STATUS_PROCESSING)
            ->where('gateway_used', 'iotec')
            ->whereNotNull('gateway_reference')
            ->where(function ($query) {
                $query->whereNull('last_reconciled_at')
                    ->orWhere('last_reconciled_at', '<=', now()->subMinutes(5));
            })
            ->oldest('requested_at')
            ->limit(max(1, (int) $this->option('limit')))
            ->get();

        foreach ($items as $item) {
            $withdrawals->reconcile($item);
        }

        $this->info("Reconciled {$items->count()} creator withdrawal(s).");

        return self::SUCCESS;
    }
}
