<?php

namespace App\Console\Commands;

use App\Services\PartnerWalletService;
use Illuminate\Console\Command;

class MarkPartnerEarningsAvailable extends Command
{
    protected $signature = 'partner:mark-earnings-available';

    protected $description = 'Move pending partner earnings to available when their hold period has passed';

    public function handle(PartnerWalletService $wallets): int
    {
        $count = $wallets->markAvailable();
        $this->info("Marked {$count} partner earnings as available.");

        return self::SUCCESS;
    }
}
