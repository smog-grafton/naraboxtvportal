<?php

namespace App\Console\Commands;

use App\Models\MaintenanceWindow;
use App\Models\SystemStatusMessage;
use Illuminate\Console\Command;

class ExpirePlatformOperations extends Command
{
    protected $signature = 'operations:expire';
    protected $description = 'Deactivate expired maintenance windows and service advisories';

    public function handle(): int
    {
        $expiredMaintenance = 0;
        MaintenanceWindow::query()
            ->where('is_active', true)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now())
            ->each(function (MaintenanceWindow $window) use (&$expiredMaintenance): void {
                $window->update(['is_active' => false]);
                $expiredMaintenance++;
            });

        $expiredMessages = 0;
        SystemStatusMessage::query()
            ->where('is_active', true)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now())
            ->each(function (SystemStatusMessage $message) use (&$expiredMessages): void {
                $message->update(['is_active' => false]);
                $expiredMessages++;
            });

        $this->info("Expired {$expiredMaintenance} maintenance window(s) and {$expiredMessages} advisory message(s).");

        return self::SUCCESS;
    }
}
