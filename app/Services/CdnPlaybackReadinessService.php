<?php

namespace App\Services;

use App\Models\VideoSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CdnPlaybackReadinessService
{
    public function __construct(
        private readonly CdnUrlDerivationService $cdnUrlDerivation,
        private readonly CdnMediaClientService $cdnMediaClientService,
    ) {}

    /**
     * Verify CDN-derived playback URLs and promote the best ready source.
     *
     * HLS wins only when master.m3u8 is genuinely readable. Until then, the
     * sourceable falls back to the optimized MP4 if it exists, then original MP4.
     */
    public function syncForSourceable(Model $sourceable, bool $queueMissingOnCdn = false, bool $force = false): array
    {
        if (! (bool) config('services.cdn.hls_readiness_check_enabled', true)) {
            return ['checked' => 0, 'promoted' => null, 'queued' => 0];
        }

        $sources = VideoSource::where('sourceable_type', $sourceable::class)
            ->where('sourceable_id', $sourceable->getKey())
            ->get();

        return $this->syncSources($sources, $queueMissingOnCdn, $force);
    }

    /**
     * @param Collection<int, VideoSource> $sources
     */
    public function syncSources(Collection $sources, bool $queueMissingOnCdn = false, bool $force = false): array
    {
        $checked = 0;
        $queued = 0;
        $promoted = null;

        foreach ($this->groupSourcesByCdnSource($sources) as $group) {
            /** @var Collection<int, VideoSource> $groupSources */
            $groupSources = $group['sources'];
            $hlsUrl = $group['hls_url'];
            $mp4PlayUrl = $group['mp4_play_url'];
            $cdnSourceId = $group['cdn_source_id'];

            $hlsSource = $this->ensureDerivedSource($groupSources, $hlsUrl, 'hls_master', 'HLS', 'm3u8');
            $mp4PlaySource = $this->ensureDerivedSource($groupSources, $mp4PlayUrl, 'mp4_play', 'Optimized MP4', 'mp4');
            $fallbackSource = $this->findFallbackSource($groupSources, $mp4PlaySource);

            $hlsReady = false;
            if ($hlsSource && $hlsUrl !== null && $this->shouldCheck($hlsSource, $force)) {
                $result = $this->checkHlsManifest($hlsUrl);
                $checked++;
                $hlsReady = $result['ready'];
                $this->markReadiness($hlsSource, $result, 'hls');
            } elseif ($hlsSource) {
                $hlsReady = (bool) (($hlsSource->metadata['cdn_ready'] ?? false) || ($hlsSource->metadata['cdn_hls_ready'] ?? false));
            }

            if ($hlsReady && $hlsSource) {
                $this->activateAndPromote($hlsSource);
                $promoted = $hlsSource->id;
                continue;
            }

            if ($hlsSource) {
                $this->deactivateUnreadySource($hlsSource);
            }

            $mp4Ready = false;
            if ($mp4PlaySource && $mp4PlayUrl !== null && $this->shouldCheck($mp4PlaySource, $force)) {
                $result = $this->checkMp4Playback($mp4PlayUrl);
                $checked++;
                $mp4Ready = $result['ready'];
                $this->markReadiness($mp4PlaySource, $result, 'mp4_play');
            } elseif ($mp4PlaySource) {
                $mp4Ready = (bool) ($mp4PlaySource->metadata['cdn_ready'] ?? false);
            }

            if ($mp4Ready && $mp4PlaySource) {
                $this->activateAndPromote($mp4PlaySource);
                $promoted = $mp4PlaySource->id;
            } elseif ($fallbackSource) {
                $this->activateAndPromote($fallbackSource);
                $promoted = $fallbackSource->id;
            }

            if (($queueMissingOnCdn || (bool) config('services.cdn.hls_auto_queue_missing', false))
                && $cdnSourceId !== null
                && ! $hlsReady
            ) {
                if ($this->queueCdnOptimization((int) $cdnSourceId)) {
                    $queued++;
                }
            }
        }

        return ['checked' => $checked, 'promoted' => $promoted, 'queued' => $queued];
    }

    /**
     * @param Collection<int, VideoSource> $sources
     * @return array<int|string, array{cdn_source_id:int|null,sources:Collection<int, VideoSource>,hls_url:?string,mp4_play_url:?string}>
     */
    private function groupSourcesByCdnSource(Collection $sources): array
    {
        $groups = [];

        foreach ($sources as $source) {
            $url = $source->file_path ?: $source->url;
            $metadata = (array) ($source->metadata ?? []);
            $derivation = is_string($url) && $url !== ''
                ? $this->cdnUrlDerivation->deriveFromCdnUrl($url)
                : null;

            $cdnSourceId = isset($metadata['cdn_source_id']) && is_numeric($metadata['cdn_source_id'])
                ? (int) $metadata['cdn_source_id']
                : ($derivation['source_id'] ?? null);

            if ($cdnSourceId === null) {
                continue;
            }

            $key = (string) $cdnSourceId;
            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'cdn_source_id' => $cdnSourceId,
                    'sources' => collect(),
                    'hls_url' => null,
                    'mp4_play_url' => null,
                ];
            }

            $groups[$key]['sources']->push($source);

            $role = $metadata['source_role'] ?? null;
            if ($role === 'hls_master' || str_ends_with(strtolower((string) $url), 'master.m3u8')) {
                $groups[$key]['hls_url'] = (string) $url;
            }
            if ($role === 'mp4_play' || str_ends_with(strtolower((string) $url), '_play.mp4')) {
                $groups[$key]['mp4_play_url'] = (string) $url;
            }

            if ($derivation !== null) {
                $groups[$key]['hls_url'] ??= $derivation['hls_master_url'];
                $groups[$key]['mp4_play_url'] ??= $derivation['play_url'];
            }
        }

        return $groups;
    }

    /**
     * @param Collection<int, VideoSource> $sources
     */
    private function ensureDerivedSource(Collection $sources, ?string $url, string $role, string $quality, string $format): ?VideoSource
    {
        if ($url === null || $url === '') {
            return null;
        }

        $existing = $sources->first(function (VideoSource $source) use ($url, $role): bool {
            $metadata = (array) ($source->metadata ?? []);
            $sourceUrl = $source->file_path ?: $source->url;

            return ($metadata['source_role'] ?? null) === $role || rtrim((string) $sourceUrl, '/') === rtrim($url, '/');
        });

        if ($existing) {
            return $existing;
        }

        /** @var VideoSource|null $template */
        $template = $sources->first();
        if (! $template) {
            return null;
        }

        $derivation = $this->cdnUrlDerivation->deriveFromCdnUrl($url);
        $metadata = [
            'source_role' => $role,
            'cdn_asset_id' => $derivation['uuid'] ?? null,
            'cdn_source_id' => $derivation['source_id'] ?? null,
            'cdn_ready' => false,
        ];

        return VideoSource::withoutEvents(fn () => VideoSource::create([
            'sourceable_type' => $template->sourceable_type,
            'sourceable_id' => $template->sourceable_id,
            'type' => 'url',
            'url' => $url,
            'file_path' => $url,
            'quality' => $quality,
            'format' => $format,
            'file_size' => config('video_sources.defaults.file_size'),
            'duration_seconds' => config('video_sources.defaults.duration_seconds'),
            'is_primary' => false,
            'is_active' => $role !== 'hls_master',
            'metadata' => $metadata,
        ]));
    }

    /**
     * @param Collection<int, VideoSource> $sources
     */
    private function findFallbackSource(Collection $sources, ?VideoSource $mp4PlaySource): ?VideoSource
    {
        return $mp4PlaySource
            ?: $sources->first(fn (VideoSource $source): bool => ($source->metadata['source_role'] ?? null) === 'original')
            ?: $sources->first(fn (VideoSource $source): bool => $source->format !== 'm3u8');
    }

    private function shouldCheck(VideoSource $source, bool $force): bool
    {
        if ($force) {
            return true;
        }

        $ttlMinutes = max(1, (int) config('services.cdn.hls_readiness_ttl_minutes', 30));
        $checkedAt = $source->metadata['cdn_readiness_checked_at'] ?? null;
        if (! is_string($checkedAt) || $checkedAt === '') {
            return true;
        }

        try {
            return Carbon::parse($checkedAt)->lt(now()->subMinutes($ttlMinutes));
        } catch (\Throwable) {
            return true;
        }
    }

    /**
     * @return array{ready:bool,status:int|null,error:?string,checked_at:string}
     */
    private function checkHlsManifest(string $url): array
    {
        try {
            /** @var Response $response */
            $response = Http::accept('application/vnd.apple.mpegurl, application/x-mpegURL, */*')
                ->connectTimeout(max(1, (int) config('services.cdn.hls_readiness_connect_timeout', 3)))
                ->timeout(max(1, (int) config('services.cdn.hls_readiness_timeout', 8)))
                ->get($url);

            $body = (string) $response->body();
            $ready = $response->successful()
                && str_contains($body, '#EXTM3U')
                && (str_contains($body, '#EXT-X-STREAM-INF') || str_contains($body, '#EXTINF') || str_contains($body, '.m3u8'));

            return [
                'ready' => $ready,
                'status' => $response->status(),
                'error' => $ready ? null : 'HLS manifest was not ready or did not contain a valid playlist.',
                'checked_at' => now()->toDateTimeString(),
            ];
        } catch (\Throwable $exception) {
            return [
                'ready' => false,
                'status' => null,
                'error' => $exception->getMessage(),
                'checked_at' => now()->toDateTimeString(),
            ];
        }
    }

    /**
     * @return array{ready:bool,status:int|null,error:?string,checked_at:string}
     */
    private function checkMp4Playback(string $url): array
    {
        try {
            $client = Http::connectTimeout(max(1, (int) config('services.cdn.hls_readiness_connect_timeout', 3)))
                ->timeout(max(1, (int) config('services.cdn.hls_readiness_timeout', 8)));

            /** @var Response $response */
            $response = $client->head($url);
            if (in_array($response->status(), [405, 501], true)) {
                $response = $client->withHeaders(['Range' => 'bytes=0-1'])->get($url);
            }

            $contentType = strtolower((string) $response->header('Content-Type', ''));
            $ready = in_array($response->status(), [200, 206], true)
                && ! str_contains($contentType, 'text/html');

            return [
                'ready' => $ready,
                'status' => $response->status(),
                'error' => $ready ? null : 'MP4 playback file is not reachable yet.',
                'checked_at' => now()->toDateTimeString(),
            ];
        } catch (\Throwable $exception) {
            return [
                'ready' => false,
                'status' => null,
                'error' => $exception->getMessage(),
                'checked_at' => now()->toDateTimeString(),
            ];
        }
    }

    /**
     * @param array{ready:bool,status:int|null,error:?string,checked_at:string} $result
     */
    private function markReadiness(VideoSource $source, array $result, string $kind): void
    {
        $metadata = array_merge((array) ($source->metadata ?? []), [
            'cdn_ready' => $result['ready'],
            'cdn_readiness_kind' => $kind,
            'cdn_readiness_checked_at' => $result['checked_at'],
            'cdn_readiness_http_status' => $result['status'],
            'cdn_readiness_error' => $result['error'],
        ]);

        if ($kind === 'hls') {
            $metadata['cdn_hls_ready'] = $result['ready'];
        }

        VideoSource::withoutEvents(function () use ($source, $metadata, $result): void {
            $source->forceFill([
                'is_active' => $result['ready'] ? true : $source->is_active,
                'metadata' => $metadata,
            ])->save();
        });
    }

    private function activateAndPromote(VideoSource $source): void
    {
        VideoSource::withoutEvents(function () use ($source): void {
            VideoSource::where('sourceable_type', $source->sourceable_type)
                ->where('sourceable_id', $source->sourceable_id)
                ->update(['is_primary' => false]);

            $source->forceFill([
                'is_active' => true,
                'is_primary' => true,
            ])->save();
        });
    }

    private function deactivateUnreadySource(VideoSource $source): void
    {
        VideoSource::withoutEvents(function () use ($source): void {
            $metadata = array_merge((array) ($source->metadata ?? []), [
                'cdn_ready' => false,
                'cdn_hls_ready' => false,
                'cdn_unready_at' => now()->toDateTimeString(),
            ]);

            $source->forceFill([
                'is_primary' => false,
                'metadata' => $metadata,
            ])->save();
        });
    }

    private function queueCdnOptimization(int $cdnSourceId): bool
    {
        try {
            $response = $this->cdnMediaClientService->queueSourceOptimization($cdnSourceId);

            return (bool) ($response['ok'] ?? false);
        } catch (\Throwable $exception) {
            Log::warning('Failed to request CDN optimization from portal readiness sync', [
                'cdn_source_id' => $cdnSourceId,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}
