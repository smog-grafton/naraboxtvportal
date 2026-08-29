<?php

namespace App\Console\Commands;

use App\Services\Storage\StorageTargetRegistry;
use App\Services\Storage\StorageUsageService;
use Illuminate\Console\Command;

class RecalculateStorageTargetUsage extends Command
{
    protected $signature = 'storage-targets:recalculate-usage
        {target? : A specific storage target key to recalculate (default: all enabled targets)}';

    protected $description = 'Recalculate storage target usage by listing bucket objects (expensive; run periodically, not per-request).';

    public function handle(StorageTargetRegistry $registry, StorageUsageService $usage): int
    {
        $requestedKey = $this->argument('target');
        $targets = $requestedKey ? [$registry->findOrFail($requestedKey)] : $registry->enabled();

        foreach ($targets as $target) {
            $this->line("Recalculating usage for [{$target->key}] ({$target->label})...");

            try {
                $bytes = $usage->recalculateLocally($target);
                $this->info("  {$target->key}: ".number_format($bytes / 1073741824, 2).' GB');
            } catch (\Throwable $exception) {
                $this->error("  {$target->key}: failed — {$exception->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
