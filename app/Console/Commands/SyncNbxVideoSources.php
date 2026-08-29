<?php

namespace App\Console\Commands;

use App\Models\VideoSource;
use App\Services\NbxVideoSourceService;
use Illuminate\Console\Command;

class SyncNbxVideoSources extends Command
{
    protected $signature = 'nbx:sync-video-sources
        {video_source_id? : Optional portal video_sources.id}
        {--movie-id=}
        {--episode-id=}
        {--limit=100 : Maximum sources to sync when no specific source is selected}';

    protected $description = 'Poll NBX Engine discovery and update portal video source metadata';

    /**
     * A source already marked failed is re-checked at most this many times
     * automatically before we stop polling it — a genuinely dead source
     * shouldn't be hammered forever, but a false failure (NBX's stale-job
     * reaper racing a still-running FFmpeg attempt) should self-heal here
     * instead of staying stuck until someone manually clicks Reprocess.
     */
    private const MAX_AUTO_RESYNC_ATTEMPTS = 8;

    /**
     * Wait this long after a failure before re-checking it, so we're not
     * hammering NBX for a source that just failed a moment ago.
     */
    private const STALE_FAILED_MINUTES = 5;

    public function handle(NbxVideoSourceService $service): int
    {
        $query = VideoSource::query()
            ->whereIn('type', ['nbx-engine', 'tele_ob'])
            ->where(function ($query): void {
                $query->whereNull('metadata->source_role')
                    ->orWhere('metadata->source_role', '!=', 'hls_master');
            });

        if ($id = $this->argument('video_source_id')) {
            $query->whereKey((int) $id);
        }

        if ($movieId = $this->option('movie-id')) {
            $query->where('sourceable_type', \App\Models\Movie::class)->where('sourceable_id', (int) $movieId);
        }

        if ($episodeId = $this->option('episode-id')) {
            $query->where('sourceable_type', \App\Models\Episode::class)->where('sourceable_id', (int) $episodeId);
        }

        if (! $this->argument('video_source_id') && ! $this->option('movie-id') && ! $this->option('episode-id')) {
            $staleFailedCutoff = now()->subMinutes(self::STALE_FAILED_MINUTES);
            $query->where(function ($query) use ($staleFailedCutoff): void {
                $query->where('metadata->nbx_sync_status', 'failed')
                    ->orWhereNull('metadata->fetch_status')
                    ->orWhereNotIn('metadata->fetch_status', ['completed', 'failed'])
                    ->orWhere(function ($query) use ($staleFailedCutoff): void {
                        $query->where('metadata->fetch_status', 'failed')
                            ->where('updated_at', '<', $staleFailedCutoff)
                            ->where(function ($query): void {
                                $query->whereNull('metadata->auto_resync_attempts')
                                    ->orWhere('metadata->auto_resync_attempts', '<', self::MAX_AUTO_RESYNC_ATTEMPTS);
                            });
                    });
            })->limit(max(1, (int) $this->option('limit')));
        }

        $synced = 0;
        $failed = 0;

        $query->chunkById(50, function ($sources) use ($service, &$synced, &$failed): void {
            foreach ($sources as $source) {
                try {
                    $wasFailed = (($source->metadata ?? [])['fetch_status'] ?? null) === 'failed';
                    $service->sync($source);
                    $synced++;

                    if ($wasFailed) {
                        $this->trackAutoResyncAttempt($source);
                    }
                } catch (\Throwable $exception) {
                    $failed++;
                    $this->warn('Failed syncing source ' . $source->id . ': ' . $exception->getMessage());
                }
            }
        });

        $this->info("Synced {$synced} NBX source(s).");
        if ($failed > 0) {
            $this->warn("Failed {$failed} NBX source(s).");
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Bump (or clear, on recovery) the counter that bounds how many times a
     * failed source gets automatically re-checked.
     */
    private function trackAutoResyncAttempt(\App\Models\VideoSource $source): void
    {
        $fresh = $source->fresh();
        if (! $fresh) {
            return;
        }

        $metadata = (array) ($fresh->metadata ?? []);
        if (($metadata['fetch_status'] ?? null) === 'failed') {
            $metadata['auto_resync_attempts'] = (int) ($metadata['auto_resync_attempts'] ?? 0) + 1;
        } else {
            unset($metadata['auto_resync_attempts']);
        }

        $fresh->update(['metadata' => $metadata]);
    }
}
