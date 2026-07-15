<?php

namespace App\Console\Commands;

use App\Services\CreatorEarningsService;
use Illuminate\Console\Command;

class MarkCreatorEarningsAvailable extends Command
{
    protected $signature = 'creator:mark-earnings-available';

    protected $description = 'Move pending creator earnings to available when hold period has passed';

    public function handle(CreatorEarningsService $service): int
    {
        $count = $service->markAvailable();
        $this->info("Marked {$count} creator earnings as available.");
        return 0;
    }
}
