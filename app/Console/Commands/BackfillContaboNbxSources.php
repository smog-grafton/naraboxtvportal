<?php

namespace App\Console\Commands;

use App\Models\Movie;
use App\Models\VideoSource;
use App\Services\ContaboObjectStorageService;
use App\Services\NbxVideoSourceService;
use Illuminate\Console\Command;

class BackfillContaboNbxSources extends Command
{
    protected $signature = 'nbx:backfill-contabo-sources
        {--dry-run : Show eligible objects without submitting NBX jobs}
        {--limit=20 : Maximum eligible objects to inspect/submit}
        {--movie-id= : Only inspect one movie id}
        {--quality=480p : Requested primary quality label}
        {--include-720p : Request 720p HLS when the source supports it}
        {--force : Submit even if an NBX backfill already exists for the object}
        {--storage=contabo : Portal storage disk label for reporting}';

    protected $description = 'Schedule NBX faststart + HLS processing for existing Contabo object-storage video files';

    private const SKIP_EXTENSIONS = ['m3u8', 'ts', 'm4s', 'jpg', 'jpeg', 'png', 'webp', 'gif', 'vtt', 'srt', 'ass', 'ssa', 'txt', 'json'];
    private const VIDEO_EXTENSIONS = ['mp4', 'm4v', 'mov', 'mkv', 'webm', 'avi', 'mpeg', 'mpg'];

    public function handle(ContaboObjectStorageService $contabo, NbxVideoSourceService $nbx): int
    {
        if (! $contabo->isConfigured()) {
            $this->error($contabo->configurationError());

            return self::FAILURE;
        }

        $limit = max(1, (int) $this->option('limit'));
        $movieId = $this->option('movie-id') !== null ? (int) $this->option('movie-id') : null;
        $prefix = $movieId ? 'videos/movies/' . $movieId : trim((string) config('services.contabo_object_storage.path_prefix', 'videos'), '/');
        $objects = $contabo->listObjects($prefix, $limit * 5);

        $summary = [
            'found' => 0,
            'matched' => 0,
            'skipped' => 0,
            'duplicates' => 0,
            'submitted' => 0,
            'failures' => 0,
        ];

        foreach ($objects as $object) {
            if ($summary['found'] >= $limit) {
                break;
            }

            $key = (string) ($object['key'] ?? '');
            $url = (string) ($object['url'] ?? '');
            if (! $this->isEligibleVideoObject($key)) {
                $summary['skipped']++;
                continue;
            }

            $summary['found']++;
            $movie = $movieId ? Movie::find($movieId) : $this->matchMovieForObject($key, $url, $contabo);
            if (! $movie) {
                $summary['skipped']++;
                $this->line('skip unmatched: ' . $key);
                continue;
            }

            $summary['matched']++;
            if (! (bool) $this->option('force') && $this->hasExistingNbxBackfill($movie, $key, $url)) {
                $summary['duplicates']++;
                $this->line('skip duplicate: movie #' . $movie->id . ' ' . $key);
                continue;
            }

            $this->line(sprintf(
                '%s movie #%d %s',
                $this->option('dry-run') ? 'dry-run' : 'submit',
                $movie->id,
                $key
            ));

            if ($this->option('dry-run')) {
                continue;
            }

            try {
                $source = $nbx->submitObjectStorageBackfill($movie, [
                    'key' => $key,
                    'url' => $url,
                    'size' => $object['size'] ?? null,
                    'disk' => (string) $this->option('storage'),
                ], [
                    'quality' => (string) $this->option('quality'),
                    'format' => strtolower((string) pathinfo($key, PATHINFO_EXTENSION)) ?: 'mp4',
                    'import_mode' => 'queue',
                    'include_720p' => (bool) $this->option('include-720p'),
                    'nbx_storage_target' => 'contabo',
                    'nbx_faststart' => true,
                    'nbx_hls_480p' => true,
                    'nbx_hls_720p' => (bool) $this->option('include-720p'),
                    'nbx_hls_1080p' => false,
                    'is_active' => true,
                    'is_primary' => false,
                ], 'movie');

                $summary['submitted']++;
                $this->info('queued NBX job ' . (($source->metadata['nbx_job_id'] ?? null) ?: 'pending') . ' for video source #' . $source->id);
            } catch (\Throwable $exception) {
                $summary['failures']++;
                $this->warn('failed: ' . $key . ' — ' . $exception->getMessage());
            }
        }

        $this->newLine();
        $this->table(['metric', 'count'], collect($summary)->map(fn (int $count, string $key): array => [$key, $count])->values()->all());

        return $summary['failures'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function isEligibleVideoObject(string $key): bool
    {
        $extension = strtolower((string) pathinfo($key, PATHINFO_EXTENSION));
        if ($extension === '' || in_array($extension, self::SKIP_EXTENSIONS, true)) {
            return false;
        }

        if (! in_array($extension, self::VIDEO_EXTENSIONS, true)) {
            return false;
        }

        $lowerKey = strtolower($key);

        return ! str_contains($lowerKey, '/hls/')
            && ! str_contains($lowerKey, '/thumbnails/')
            && ! str_contains($lowerKey, '/subtitles/')
            && ! str_contains($lowerKey, '/tmp/')
            && ! str_contains($lowerKey, '/temp/');
    }

    private function matchMovieForObject(string $key, string $url, ContaboObjectStorageService $contabo): ?Movie
    {
        if (preg_match('#(?:^|/)movies/(\d+)(?:/|$)#', $key, $matches) === 1) {
            $movie = Movie::find((int) $matches[1]);
            if ($movie) {
                return $movie;
            }
        }

        $source = VideoSource::query()
            ->whereIn('type', ['contabo_object_storage', 'url', 'nbx-engine'])
            ->where(function ($query) use ($url, $key, $contabo): void {
                $query->where('url', $url)
                    ->orWhere('file_path', $url)
                    ->orWhere('metadata->object_key', $key)
                    ->orWhere('metadata->contabo_key', $key)
                    ->orWhere('metadata->public_url', $url)
                    ->orWhere('metadata->download_url', $url);

                if ($contabo->isContaboPublicUrl($url)) {
                    $query->orWhere('url', $contabo->publicUrl($key));
                }
            })
            ->latest('id')
            ->first();

        return $source?->sourceable instanceof Movie ? $source->sourceable : null;
    }

    private function hasExistingNbxBackfill(Movie $movie, string $key, string $url): bool
    {
        return $movie->videoSources()
            ->where('type', 'nbx-engine')
            ->get()
            ->contains(function (VideoSource $source) use ($key, $url): bool {
                $metadata = (array) ($source->metadata ?? []);
                $backfill = is_array($metadata['nbx_backfill'] ?? null) ? $metadata['nbx_backfill'] : [];

                return ($backfill['object_key'] ?? null) === $key
                    || ($backfill['object_url'] ?? null) === $url
                    || ($metadata['original_url'] ?? null) === $url
                    || ($metadata['download_url'] ?? null) === $url;
            });
    }
}
