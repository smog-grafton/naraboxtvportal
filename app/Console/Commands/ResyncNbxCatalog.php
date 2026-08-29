<?php

namespace App\Console\Commands;

use App\Jobs\RecreateNbxSourceJob;
use App\Jobs\RediscoverNbxSourceJob;
use App\Models\Episode;
use App\Models\Movie;
use App\Services\NbxCatalogRecoveryService;
use App\Services\ResyncRunTracker;
use Illuminate\Console\Command;

class ResyncNbxCatalog extends Command
{
    protected $signature = 'nbx:resync-catalog
        {--tier=1 : 1 = cheap re-discovery against NBX (default). 2 = full re-fetch/re-transcode — only for sources tier 1 could not match, costs real bandwidth/compute.}
        {--movie-id=}
        {--episode-id=}
        {--limit=500 : Maximum sources to queue in one run}
        {--dry-run : Report how many sources are eligible without queuing anything}';

    protected $description = 'Bulk-resync Portal video_sources against NBX after its catalog was rebuilt from Contabo storage';

    public function handle(NbxCatalogRecoveryService $recovery): int
    {
        $tier = (int) $this->option('tier');
        if (! in_array($tier, [1, 2], true)) {
            $this->error('--tier must be 1 or 2.');

            return self::FAILURE;
        }

        $query = $tier === 1
            ? $recovery->eligibleForRediscoveryQuery()
            : $recovery->eligibleForRecreationQuery();

        if ($movieId = $this->option('movie-id')) {
            $query->where('sourceable_type', Movie::class)->where('sourceable_id', (int) $movieId);
        }
        if ($episodeId = $this->option('episode-id')) {
            $query->where('sourceable_type', Episode::class)->where('sourceable_id', (int) $episodeId);
        }

        $total = (clone $query)->limit((int) $this->option('limit'))->count();

        if ($total === 0) {
            $this->info('No eligible video sources found for tier '.$tier.'.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("{$total} video source(s) are eligible for tier {$tier} resync. Nothing was queued (--dry-run).");

            return self::SUCCESS;
        }

        // Only prompt when actually attached to a terminal. When this
        // command is invoked programmatically (Artisan::call from the
        // Filament resync page, or --no-interaction from a script), the
        // caller is responsible for having already obtained confirmation —
        // Laravel's confirm() defaults to false with no TTY, which would
        // otherwise silently abort every non-interactive tier-2 run.
        if ($tier === 2 && $this->input->isInteractive() && ! $this->confirm(
            "Tier 2 re-fetches and re-transcodes {$total} video(s) from their original source URL — this costs real bandwidth and NBX compute. Continue?",
            false,
        )) {
            $this->warn('Aborted.');

            return self::SUCCESS;
        }

        $runKey = ResyncRunTracker::start('tier'.$tier, $total);
        $queued = 0;

        $query->limit((int) $this->option('limit'))->chunkById(50, function ($sources) use ($tier, $runKey, &$queued): void {
            foreach ($sources as $source) {
                $tier === 1
                    ? RediscoverNbxSourceJob::dispatch($source->id, $runKey)
                    : RecreateNbxSourceJob::dispatch($source->id, $runKey);
                $queued++;
            }
        });

        $this->info("Queued {$queued} tier-{$tier} resync job(s). Run key: {$runKey}");

        return self::SUCCESS;
    }
}
