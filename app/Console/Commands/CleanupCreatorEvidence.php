<?php

namespace App\Console\Commands;

use App\Models\CreatorVerificationEvidence;
use App\Services\CreatorAuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupCreatorEvidence extends Command
{
    protected $signature = 'creator:cleanup-evidence {--limit=500}';

    protected $description = 'Delete expired private creator-verification evidence and retain an audit-safe tombstone.';

    public function handle(CreatorAuditService $audit): int
    {
        $deleted = 0;
        CreatorVerificationEvidence::query()
            ->whereNull('deleted_at')
            ->whereNotNull('retention_until')
            ->where('retention_until', '<=', now())
            ->orderBy('id')
            ->limit(max(1, (int) $this->option('limit')))
            ->get()
            ->each(function (CreatorVerificationEvidence $evidence) use (&$deleted, $audit): void {
                if (Storage::disk($evidence->disk)->exists($evidence->path)
                    && ! Storage::disk($evidence->disk)->delete($evidence->path)) {
                    $this->warn("Could not delete evidence {$evidence->id}; it will be retried.");

                    return;
                }
                $evidence->update([
                    'status' => 'deleted_by_retention',
                    'deleted_at' => now(),
                    'path' => 'deleted/'.hash('sha256', (string) $evidence->path),
                ]);
                $audit->record('creator.evidence_retention_deleted', null, $evidence->user, $evidence);
                $deleted++;
            });

        $this->info("Deleted {$deleted} expired creator evidence file(s).");

        return self::SUCCESS;
    }
}
