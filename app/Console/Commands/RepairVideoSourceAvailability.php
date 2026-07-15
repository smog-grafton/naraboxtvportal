<?php

namespace App\Console\Commands;

use App\Models\DownloadSource;
use App\Models\Episode;
use App\Models\Movie;
use App\Models\VideoSource;
use App\Services\VideoSourceDerivationService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class RepairVideoSourceAvailability extends Command
{
    protected $signature = 'video-sources:repair-availability
        {--dry-run : Report changes without writing}
        {--limit=100 : Maximum video sources to inspect}
        {--movie-id= : Restrict to one movie ID}
        {--episode-id= : Restrict to one episode ID}
        {--activate-valid : Reactivate inactive sources that have usable non-HLS URLs}
        {--sync-downloads : Sync MP4 sources to download_sources}
        {--derive-cdn-siblings : Recreate CDN MP4/HLS sibling source records when derivable}';

    protected $description = 'Safely repair video source availability without letting NBX break legacy Contabo/direct/Bunny sources';

    public function handle(VideoSourceDerivationService $derivationService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $activateValid = (bool) $this->option('activate-valid');
        $syncDownloads = (bool) $this->option('sync-downloads');
        $deriveSiblings = (bool) $this->option('derive-cdn-siblings');
        $limit = max(1, (int) $this->option('limit'));

        $query = VideoSource::query()
            ->where(function (Builder $query): void {
                $query->whereNotNull('url')
                    ->orWhereNotNull('file_path')
                    ->orWhereNotNull('metadata->download_url')
                    ->orWhereNotNull('metadata->mp4_play_url')
                    ->orWhereNotNull('metadata->mp4_url')
                    ->orWhereNotNull('metadata->original_url');
            })
            ->latest('id')
            ->limit($limit);

        if ($movieId = $this->option('movie-id')) {
            $query->where('sourceable_type', Movie::class)->where('sourceable_id', (int) $movieId);
        }

        if ($episodeId = $this->option('episode-id')) {
            $query->where('sourceable_type', Episode::class)->where('sourceable_id', (int) $episodeId);
        }

        $checked = 0;
        $reactivated = 0;
        $downloadSynced = 0;
        $derived = 0;
        $skippedHlsDownloads = 0;

        $query->chunkById(100, function ($sources) use (
            $dryRun,
            $activateValid,
            $syncDownloads,
            $deriveSiblings,
            $derivationService,
            &$checked,
            &$reactivated,
            &$downloadSynced,
            &$derived,
            &$skippedHlsDownloads,
        ): void {
            foreach ($sources as $source) {
                $checked++;
                $url = $this->playableUrl($source);
                if (! $url) {
                    continue;
                }

                $isHls = $this->isHlsUrl($url, $source->format);

                if (! $source->is_active && ! $isHls && $activateValid) {
                    $reactivated++;
                    $this->line(($dryRun ? '[dry-run] ' : '') . "Reactivate source #{$source->id} ({$source->type}) {$url}");

                    if (! $dryRun) {
                        VideoSource::withoutEvents(function () use ($source): void {
                            $metadata = array_merge((array) ($source->metadata ?? []), [
                                'repair_reactivated_at' => now()->toDateTimeString(),
                                'repair_reactivated_reason' => 'valid_non_hls_url',
                            ]);

                            $source->forceFill([
                                'is_active' => true,
                                'metadata' => $metadata,
                            ])->save();
                        });
                    }
                }

                if ($deriveSiblings && ! $dryRun) {
                    $before = VideoSource::where('sourceable_type', $source->sourceable_type)
                        ->where('sourceable_id', $source->sourceable_id)
                        ->count();
                    $derivationService->ensureDerivedSourcesForCdnUrl($source->fresh() ?? $source);
                    $after = VideoSource::where('sourceable_type', $source->sourceable_type)
                        ->where('sourceable_id', $source->sourceable_id)
                        ->count();
                    $derived += max(0, $after - $before);
                } elseif ($deriveSiblings) {
                    $this->line("[dry-run] Would derive CDN siblings for source #{$source->id} when URL matches CDN pattern.");
                }

                if ($syncDownloads) {
                    if ($isHls) {
                        $skippedHlsDownloads++;
                        continue;
                    }

                    $downloadSynced++;
                    $this->line(($dryRun ? '[dry-run] ' : '') . "Sync download for source #{$source->id} {$url}");

                    if (! $dryRun) {
                        ($source->fresh() ?? $source)->syncToDownloadSource();
                    }
                }
            }
        });

        $this->info("Checked {$checked} video source(s).");
        $this->info("Reactivated {$reactivated} valid source(s).");
        $this->info("Synced {$downloadSynced} MP4 download source(s).");
        $this->info("Derived {$derived} CDN sibling source(s).");
        $this->info("Skipped {$skippedHlsDownloads} HLS download candidate(s).");

        if (! $activateValid && ! $syncDownloads && ! $deriveSiblings) {
            $this->warn('No write action selected. Add --activate-valid and/or --sync-downloads after reviewing --dry-run output.');
        }

        return self::SUCCESS;
    }

    private function playableUrl(VideoSource $source): ?string
    {
        $metadata = is_array($source->metadata) ? $source->metadata : [];

        foreach ([
            $source->full_url,
            $source->url,
            $source->file_path,
            $metadata['mp4_play_url'] ?? null,
            $metadata['mp4_url'] ?? null,
            $metadata['original_url'] ?? null,
            $metadata['download_url'] ?? null,
            $metadata['hls_master_url'] ?? null,
        ] as $url) {
            if (is_string($url) && trim($url) !== '') {
                return trim($url);
            }
        }

        return null;
    }

    private function isHlsUrl(string $url, ?string $format): bool
    {
        if (in_array(strtolower((string) $format), ['m3u8', 'hls'], true)) {
            return true;
        }

        $path = strtolower((string) parse_url($url, PHP_URL_PATH));

        return str_ends_with($path, '.m3u8') || str_ends_with($path, '.ts');
    }
}
