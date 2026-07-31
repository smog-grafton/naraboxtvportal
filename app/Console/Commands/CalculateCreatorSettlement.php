<?php

namespace App\Console\Commands;

use App\Services\CreatorSettlementService;
use Illuminate\Console\Command;

class CalculateCreatorSettlement extends Command
{
    protected $signature = 'creator:calculate-settlement {period? : Settlement period in YYYY-MM format}';

    protected $description = 'Calculate a reviewable subscription creator-pool settlement from qualified engagement';

    public function handle(CreatorSettlementService $settlements): int
    {
        $period = (string) ($this->argument('period') ?: now()->subMonthNoOverflow()->format('Y-m'));
        $settlement = $settlements->calculate($period);
        $this->info("Settlement {$period} is {$settlement->status}; pool {$settlement->creator_pool_minor} UGX.");

        return self::SUCCESS;
    }
}
