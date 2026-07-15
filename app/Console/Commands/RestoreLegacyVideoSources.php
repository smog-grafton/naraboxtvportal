<?php

namespace App\Console\Commands;

use App\Models\Episode;
use App\Models\Movie;
use App\Models\VideoSource;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class RestoreLegacyVideoSources extends Command
{
    protected $signature = 'video-sources:restore-legacy
        {--dry-run : Report changes without writing}
        {--limit=100 : Maximum video sources to inspect}
        {--source-type=* : Restrict to one or more source types}
        {--movie-id= : Restrict to one movie ID}
        {--episode-id= : Restrict to one episode ID}
        {--activate-valid : Reactivate inactive legacy sources with usable non-HLS URLs}
        {--sync-downloads : Sync usable MP4 sources to download_sources when downloads are enabled}';

    protected $description = 'Safely restore legacy Contabo/fetched/curl/direct/local video sources without replacing NBX sources';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $activateValid = (bool) $this->option('activate-valid');
        $syncDownloads = (bool) $this->option('sync-downloads');
        $limit = max(1, (int) $this->option('limit'));
        $sourceTypes = array_values(array_filter((array) $this->option('source-type')));
        $sourceTypes = $sourceTypes ?: $this->legacySourceTypes();

        $query = VideoSource::query()
            ->with('sourceable')
            ->whereIn('type', $sourceTypes)
            ->where(function (Builder $query): void {
                $query->whereNotNull('url')
                    ->orWhereNotNull('file_path')
                    ->orWhereNotNull('metadata->public_url')
                    ->orWhereNotNull('metadata->source_url')
                    ->orWhereNotNull('metadata->download_url')
                    ->orWhereNotNull('metadata->download_mp4_url')
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
        $valid = 0;
        $inactiveValid = 0;
        $reactivated = 0;
        $downloadSynced = 0;
        $skippedHlsDownloads = 0;
        $brokenSourceables = [];

        foreach ($query->get() as $source) {
            $checked++;
            $url = $this->sourceUrl($source);
            $isHls = $this->isHls($source, $url);
            $isValid = $url !== null && ! $this->isFailed($source);

            if ($isValid) {
                $valid++;
            }

            if ($isValid && ! $source->is_active) {
                $inactiveValid++;
                $this->line(($dryRun ? '[dry-run] ' : '') . "Inactive valid source #{$source->id} {$source->type} {$url}");

                if ($activateValid && ! $isHls) {
                    $reactivated++;

                    if (! $dryRun) {
                        VideoSource::withoutEvents(function () use ($source): void {
                            $metadata = array_merge((array) ($source->metadata ?? []), [
                                'legacy_restored_at' => now()->toDateTimeString(),
                                'legacy_restored_reason' => 'valid_legacy_url',
                            ]);

                            $source->forceFill([
                                'is_active' => true,
                                'metadata' => $metadata,
                            ])->save();
                        });
                    }
                }
            }

            if ($source->sourceable && ! $this->sourceableHasPlayableSource($source->sourceable)) {
                $brokenSourceables[$source->sourceable_type . ':' . $source->sourceable_id] = [
                    class_basename($source->sourceable_type),
                    $source->sourceable_id,
                    $source->sourceable->title ?? ('Episode #' . $source->sourceable_id),
                ];
            }

            if (! $syncDownloads || ! $isValid) {
                continue;
            }

            if ($isHls) {
                $skippedHlsDownloads++;
                continue;
            }

            if (! (bool) ($source->sourceable?->download_enabled ?? false)) {
                continue;
            }

            $downloadSynced++;
            $this->line(($dryRun ? '[dry-run] ' : '') . "Sync download for source #{$source->id} {$source->type} {$url}");

            if (! $dryRun) {
                ($source->fresh() ?? $source)->syncToDownloadSource();
            }
        }

        $this->info("Checked {$checked} legacy video source(s).");
        $this->info("Valid URL source(s): {$valid}.");
        $this->info("Inactive valid source(s): {$inactiveValid}.");
        $this->info("Reactivated source(s): {$reactivated}.");
        $this->info("Synced download source(s): {$downloadSynced}.");
        $this->info("Skipped HLS download candidate(s): {$skippedHlsDownloads}.");

        if ($brokenSourceables !== []) {
            $this->warn('Sourceables still resolving to no playable source in inspected set:');
            $this->table(['Type', 'ID', 'Title'], array_slice(array_values($brokenSourceables), 0, 20));
        }

        if (! $activateValid && ! $syncDownloads) {
            $this->warn('No write action selected. Add --activate-valid and/or --sync-downloads after reviewing dry-run output.');
        }

        return self::SUCCESS;
    }

    private function legacySourceTypes(): array
    {
        return [
            'contabo',
            'contabo_object_storage',
            'fetched',
            'curl',
            'local',
            'url',
            'direct',
            'upload',
            'uploaded',
            'cdn',
            'legacy_cdn',
            'tele_ob',
            'bunny_stream',
            'nbx-engine',
        ];
    }

    private function sourceableHasPlayableSource(object $sourceable): bool
    {
        if (! method_exists($sourceable, 'videoSources')) {
            return false;
        }

        return $sourceable->videoSources()
            ->get()
            ->contains(fn (VideoSource $source): bool => $this->sourceUrl($source) !== null && ! $this->isFailed($source));
    }

    private function sourceUrl(VideoSource $source): ?string
    {
        $metadata = is_array($source->metadata) ? $source->metadata : [];

        foreach ([
            $source->full_url,
            $source->url,
            $source->file_path,
            $metadata['mp4_play_url'] ?? null,
            $metadata['mp4_url'] ?? null,
            $metadata['download_mp4_url'] ?? null,
            $metadata['download_url'] ?? null,
            $metadata['original_url'] ?? null,
            $metadata['public_url'] ?? null,
            $metadata['source_url'] ?? null,
            $metadata['hls_master_url'] ?? null,
            $metadata['hls_url'] ?? null,
        ] as $url) {
            if (is_string($url) && trim($url) !== '') {
                return trim($url);
            }
        }

        return null;
    }

    private function isFailed(VideoSource $source): bool
    {
        $metadata = is_array($source->metadata) ? $source->metadata : [];

        foreach (['status', 'fetch_status', 'cdn_status', 'nbx_status'] as $key) {
            if (in_array(strtolower((string) ($metadata[$key] ?? '')), ['failed', 'error', 'missing'], true)) {
                return true;
            }
        }

        return false;
    }

    private function isHls(VideoSource $source, ?string $url): bool
    {
        if (in_array(strtolower((string) $source->format), ['m3u8', 'hls'], true)) {
            return true;
        }

        $path = strtolower((string) parse_url((string) $url, PHP_URL_PATH));

        return str_ends_with($path, '.m3u8') || str_ends_with($path, '.ts');
    }
}
