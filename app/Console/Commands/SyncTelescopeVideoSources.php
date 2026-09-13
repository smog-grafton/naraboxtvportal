<?php

namespace App\Console\Commands;

use App\Models\VideoSource;
use App\Services\TelescopeImportService;
use Illuminate\Console\Command;

class SyncTelescopeVideoSources extends Command
{
    protected $signature = 'telescope:sync-video-sources
        {video_source_id? : Optional portal video_sources.id}
        {--limit=100 : Maximum non-terminal Telescope sources to poll}';

    protected $description = 'Poll Teletyde as a fallback when Telescope callbacks are delayed or missed';

    public function handle(TelescopeImportService $telescope): int
    {
        $query = VideoSource::query()
            ->where('type', 'telescope')
            ->whereNotNull('processing_job_id');

        if ($sourceId = $this->argument('video_source_id')) {
            $query->whereKey((int) $sourceId);
        } else {
            $query->where(function ($query): void {
                $query->whereNull('metadata->telescope_status')
                    ->orWhereNotIn('metadata->telescope_status', ['ready', 'failed', 'cancelled']);
            })->oldest('updated_at')->limit(max(1, (int) $this->option('limit')));
        }

        $synced = 0;
        $failed = 0;
        $query->each(function (VideoSource $source) use ($telescope, &$synced, &$failed): void {
            try {
                $telescope->sync($source);
                $synced++;
            } catch (\Throwable $exception) {
                $failed++;
                $this->warn("Failed syncing Telescope source {$source->id}: {$exception->getMessage()}");
            }
        });

        $this->info("Synced {$synced} Telescope source(s); {$failed} failed.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
