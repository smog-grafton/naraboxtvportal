<?php

namespace App\Console\Commands;

use App\Models\DownloadSource;
use App\Models\Episode;
use App\Models\Movie;
use App\Models\VideoSource;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class DiagnoseVideoSources extends Command
{
    protected $signature = 'video-sources:diagnose
        {id? : Optional movie or episode ID}
        {--limit=50 : Maximum sourceables/sources to display}
        {--source-type= : Restrict to one source type}
        {--only-broken : Only show records resolving to no playable source}
        {--movie-id= : Diagnose one movie ID}
        {--episode-id= : Diagnose one episode ID}';

    protected $description = 'Diagnose playback/download availability across legacy and NBX video sources';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $sourceType = $this->option('source-type') ? (string) $this->option('source-type') : null;
        $onlyBroken = (bool) $this->option('only-broken');
        $movieId = $this->option('movie-id') ?: null;
        $episodeId = $this->option('episode-id') ?: null;
        $argumentId = $this->argument('id') ?: null;

        if (! $movieId && ! $episodeId && $argumentId) {
            $movieId = $argumentId;
        }

        $this->info('Video source totals by type/format/active:');
        $this->table(
            ['Type', 'Format', 'Active', 'Total', 'With URL'],
            VideoSource::query()
                ->select([
                    'type',
                    DB::raw("COALESCE(format, '(null)') AS source_format"),
                    'is_active',
                    DB::raw('COUNT(*) AS total'),
                    DB::raw("SUM((url IS NOT NULL AND url <> '') OR (file_path IS NOT NULL AND file_path <> '') OR JSON_EXTRACT(metadata, '$.mp4_url') IS NOT NULL OR JSON_EXTRACT(metadata, '$.download_url') IS NOT NULL OR JSON_EXTRACT(metadata, '$.original_url') IS NOT NULL) AS with_url"),
                ])
                ->groupBy('type', 'source_format', 'is_active')
                ->orderByDesc('total')
                ->get()
                ->map(fn ($row): array => [
                    $row->type,
                    $row->source_format,
                    (int) $row->is_active,
                    (int) $row->total,
                    (int) $row->with_url,
                ])
                ->all()
        );

        $inactiveWithUrl = VideoSource::query()
            ->where('is_active', false)
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
            ->count();

        $this->line('Movies: ' . Movie::count() . ' total.');
        $this->line('Episodes: ' . Episode::count() . ' total.');
        $this->line("Inactive sources with URL candidates: {$inactiveWithUrl}.");

        $sourceQuery = VideoSource::query()
            ->with('sourceable')
            ->latest('id')
            ->limit($limit);

        if ($sourceType) {
            $sourceQuery->where('type', $sourceType);
        }

        if ($movieId) {
            $sourceQuery->where('sourceable_type', Movie::class)->where('sourceable_id', (int) $movieId);
        }

        if ($episodeId) {
            $sourceQuery->where('sourceable_type', Episode::class)->where('sourceable_id', (int) $episodeId);
        }

        $rows = [];
        $broken = [];

        foreach ($sourceQuery->get() as $source) {
            $url = $this->sourceUrl($source);
            $reason = $this->rejectionReason($source, $url);
            $playable = $reason === 'playable';
            $selected = $source->sourceable ? $this->selectedSourceFor($source->sourceable) : null;
            $sourceableKey = $source->sourceable_type . ':' . $source->sourceable_id;
            $sourceableBroken = $source->sourceable && $selected === null;

            if ($sourceableBroken) {
                $broken[$sourceableKey] = [
                    class_basename($source->sourceable_type),
                    $source->sourceable_id,
                    $source->sourceable->title ?? ('Episode #' . $source->sourceable_id),
                    $source->type,
                    $reason,
                ];
            }

            if ($onlyBroken && ! $sourceableBroken) {
                continue;
            }

            $rows[] = [
                $source->id,
                class_basename($source->sourceable_type),
                $source->sourceable_id,
                $source->type,
                $source->quality ?: '(auto)',
                $source->format ?: '(auto)',
                $source->is_active ? 'yes' : 'no',
                $playable ? 'yes' : 'no',
                $reason,
                $selected?->id,
                $this->shorten((string) $url),
            ];
        }

        $this->info('Inspected video sources:');
        $this->table(
            ['ID', 'Owner', 'Owner ID', 'Type', 'Quality', 'Format', 'Active', 'Playable', 'Reason', 'Selected', 'URL'],
            $rows
        );

        $this->info('Download source totals by type/format/active:');
        $this->table(
            ['Type', 'Format', 'Active', 'Total'],
            DownloadSource::query()
                ->select([
                    'type',
                    DB::raw("COALESCE(format, '(null)') AS source_format"),
                    'is_active',
                    DB::raw('COUNT(*) AS total'),
                ])
                ->groupBy('type', 'source_format', 'is_active')
                ->orderByDesc('total')
                ->get()
                ->map(fn ($row): array => [
                    $row->type,
                    $row->source_format,
                    (int) $row->is_active,
                    (int) $row->total,
                ])
                ->all()
        );

        if ($broken !== []) {
            $this->warn('Sample sourceables resolving to no playable source:');
            $this->table(['Type', 'ID', 'Title', 'Last Source Type', 'Last Reason'], array_slice(array_values($broken), 0, $limit));
        } else {
            $this->info('No broken sourceables found in inspected set.');
        }

        return self::SUCCESS;
    }

    private function selectedSourceFor(object $sourceable): ?VideoSource
    {
        if (! method_exists($sourceable, 'videoSources')) {
            return null;
        }

        return $sourceable->videoSources()
            ->get()
            ->filter(fn (VideoSource $source): bool => $this->rejectionReason($source, $this->sourceUrl($source)) === 'playable')
            ->sortBy(fn (VideoSource $source): int => $this->sourceScore($source))
            ->first();
    }

    private function sourceScore(VideoSource $source): int
    {
        $type = strtolower((string) $source->type);
        $format = strtolower((string) $source->format);
        $quality = strtolower((string) $source->quality);
        $url = strtolower((string) $this->sourceUrl($source));
        $isHls = in_array($format, ['hls', 'm3u8'], true) || str_contains($url, '.m3u8');
        $score = 900;

        if ($type === 'nbx-engine' && $isHls && str_contains($quality, '480')) {
            $score = 10;
        } elseif ($type === 'nbx-engine' && $isHls && str_contains($quality, '720')) {
            $score = 20;
        } elseif ($type === 'nbx-engine' && ! $isHls) {
            $score = 40;
        } elseif (in_array($type, ['contabo', 'contabo_object_storage', 'tele_ob'], true)) {
            $score = 50;
        } elseif (in_array($type, ['url', 'direct', 'upload', 'uploaded', 'local', 'fetched', 'curl', 'cdn', 'legacy_cdn'], true)) {
            $score = 60;
        } elseif ($type === 'bunny_stream') {
            $score = 70;
        }

        return $source->is_primary ? max(1, $score - 5) : $score;
    }

    private function rejectionReason(VideoSource $source, ?string $url): string
    {
        if ($url === null || trim($url) === '') {
            return 'missing_url';
        }

        if ($this->isFailed($source)) {
            return 'explicitly_failed';
        }

        if (! $source->is_active && $this->isHls($source, $url)) {
            return 'inactive_hls_not_ready';
        }

        return 'playable';
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

    private function isHls(VideoSource $source, string $url): bool
    {
        if (in_array(strtolower((string) $source->format), ['m3u8', 'hls'], true)) {
            return true;
        }

        $path = strtolower((string) parse_url($url, PHP_URL_PATH));

        return str_ends_with($path, '.m3u8') || str_ends_with($path, '.ts');
    }

    private function shorten(string $value): string
    {
        return strlen($value) > 96 ? substr($value, 0, 93) . '...' : $value;
    }
}
