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

    public function handle(NbxVideoSourceService $service): int
    {
        $query = VideoSource::query()
            ->where('type', 'nbx-engine')
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
            $query->where(function ($query): void {
                $query->whereNull('metadata->fetch_status')
                    ->orWhereNotIn('metadata->fetch_status', ['completed', 'failed']);
            })->limit(max(1, (int) $this->option('limit')));
        }

        $synced = 0;
        $failed = 0;

        $query->chunkById(50, function ($sources) use ($service, &$synced, &$failed): void {
            foreach ($sources as $source) {
                try {
                    $service->sync($source);
                    $synced++;
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
}
