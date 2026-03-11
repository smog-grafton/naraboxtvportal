<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VideoSource;
use App\Services\CdnMediaClientService;
use App\Services\CdnUrlDerivationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VideoFetchController extends Controller
{
    /**
     * Fetch a video from URL and store it on the CDN server.
     */
    public function fetch(Request $request)
    {
        $request->validate([
            'url' => 'required|url',
            'sourceable_type' => 'required|string|in:App\Models\Movie,App\Models\Episode',
            'sourceable_id' => 'required|integer|exists:' . ($request->input('sourceable_type') === 'App\Models\Movie' ? 'movies' : 'episodes') . ',id',
            'quality' => 'nullable|string|max:50',
            'format' => 'nullable|string|max:10',
            'import_mode' => 'nullable|string|in:now,queue',
            'import_strategy' => 'nullable|string|in:auto,python_worker',
        ]);

        $url = $this->normalizeRemoteUrl((string) $request->input('url'));
        $sourceableType = (string) $request->input('sourceable_type');
        $sourceableId = (int) $request->input('sourceable_id');
        $quality = (string) ($request->input('quality') ?? 'auto');
        $format = (string) ($request->input('format') ?? 'auto');
        $importMode = (string) ($request->input('import_mode') ?? (string) config('services.cdn.default_import_mode', 'now'));
        $requestedStrategy = (string) ($request->input('import_strategy') ?? 'auto');
        $importStrategy = $this->resolveImportStrategy($url, $requestedStrategy);
        $assetType = $sourceableType === 'App\Models\Episode' ? 'episode' : 'movie';

        try {
            $cdnService = app(CdnMediaClientService::class);

            // Manual/direct mode: if user pasted a CDN public URL, store it as a normal URL source.
            if ($this->isCdnManagedUrl($url)) {
                $directSource = $this->upsertDirectCdnUrlSource(
                    $url,
                    $sourceableType,
                    $sourceableId,
                    $quality,
                    $format,
                    $cdnService
                );

                return response()->json([
                    'success' => true,
                    'message' => 'CDN URL saved directly as playback source (no refetch).',
                    'video_source' => [
                        'id' => $directSource->id,
                        'file_path' => $directSource->file_path,
                        'file_size' => $directSource->file_size,
                        'format' => $directSource->format,
                        'quality' => $directSource->quality,
                        'type' => $directSource->type,
                        'status' => (string) (($directSource->metadata['cdn_status'] ?? 'ready')),
                        'cdn_asset_id' => $directSource->metadata['cdn_asset_id'] ?? null,
                        'cdn_source_id' => $directSource->metadata['cdn_source_id'] ?? null,
                    ],
                    'file_size' => $directSource->file_size,
                    'file_path' => $directSource->file_path,
                ]);
            }

            $existingSource = VideoSource::where('sourceable_type', $sourceableType)
                ->where('sourceable_id', $sourceableId)
                ->where('url', $url)
                ->where('type', 'fetched')
                ->first();

            $title = $sourceableType === 'App\Models\Episode'
                ? ('Episode ' . $sourceableId)
                : ('Movie ' . $sourceableId);

            try {
                $cdnImport = $cdnService->importFromUrl(
                    $url,
                    $title,
                    $assetType,
                    'public',
                    null,
                    $importMode,
                    $importStrategy
                );
            } catch (\Throwable $importError) {
                if (! $this->isTlsConnectionReset($importError)) {
                    throw $importError;
                }

                Log::warning('CDN import response connection reset; attempting URL lookup recovery', [
                    'source_url' => $url,
                    'import_mode' => $importMode,
                    'error' => $importError->getMessage(),
                ]);

                $lookup = null;
                try {
                    $lookup = $cdnService->lookupSourceByUrl($url);
                } catch (\Throwable $lookupError) {
                    Log::warning('CDN URL lookup failed after import connection reset', [
                        'source_url' => $url,
                        'error' => $lookupError->getMessage(),
                    ]);
                }

                if (! (($lookup['ok'] ?? false) && is_array($lookup['data'] ?? null))) {
                    // If lookup misses, re-submit using queue mode so the request returns fast.
                    try {
                        $queuedImport = $cdnService->importFromUrl(
                            $url,
                            $title,
                            $assetType,
                            'public',
                            null,
                            'queue',
                            $importStrategy
                        );
                    } catch (\Throwable) {
                        throw $importError;
                    }

                    if (! ($queuedImport['ok'] ?? false)) {
                        throw $importError;
                    }

                    $cdnImport = $queuedImport;
                    goto import_recovered;
                }

                $lookupData = (array) ($lookup['data'] ?? []);
                $cdnImport = [
                    'ok' => true,
                    'status_code' => (int) ($lookup['status_code'] ?? 200),
                    'error' => null,
                    'data' => [
                        'asset_id' => $lookupData['asset_id'] ?? null,
                        'source_id' => $lookupData['source_id'] ?? null,
                        'status' => $lookupData['status'] ?? 'pending',
                        'failure_reason' => $lookupData['failure_reason'] ?? null,
                        'file_size_bytes' => $lookupData['file_size_bytes'] ?? ($lookupData['bytes_total'] ?? null),
                        'public_url_if_ready' => $lookupData['public_url'] ?? null,
                    ],
                    'body' => $lookup['body'] ?? null,
                ];
            }
            import_recovered:

            if (! $cdnImport['ok']) {
                return response()->json([
                    'success' => false,
                    'message' => $cdnImport['error'] ?: 'Failed to fetch video on CDN.',
                ], 400);
            }

            $cdnData = (array) ($cdnImport['data'] ?? []);
            $cdnSourceId = isset($cdnData['source_id']) ? (int) $cdnData['source_id'] : null;
            $cdnAssetId = $cdnData['asset_id'] ?? null;
            $cdnStatus = (string) ($cdnData['status'] ?? 'pending');
            $cdnFailure = $cdnData['failure_reason'] ?? null;
            $cdnPublicUrl = $cdnData['public_url_if_ready'] ?? null;
            $cdnFileSize = null;

            $playback = null;
            if ($cdnSourceId) {
                // Some shared hosts intermittently fail outbound TLS to the same domain.
                // Keep import successful even if this follow-up status call fails.
                try {
                    $sourceInfo = $cdnService->getSource($cdnSourceId);
                    if (($sourceInfo['ok'] ?? false) && is_array($sourceInfo['data'] ?? null)) {
                        $sourceData = $sourceInfo['data'];
                        $cdnStatus = (string) ($sourceData['status'] ?? $cdnStatus);
                        $cdnFailure = $sourceData['failure_reason'] ?? $cdnFailure;
                        $cdnPublicUrl = $sourceData['public_url'] ?? $cdnPublicUrl;
                        $cdnFileSize = isset($sourceData['file_size_bytes'])
                            ? (int) $sourceData['file_size_bytes']
                            : (isset($sourceData['bytes_total']) ? (int) $sourceData['bytes_total'] : null);
                        $playback = isset($sourceData['playback']) && is_array($sourceData['playback']) ? $sourceData['playback'] : null;
                    }
                } catch (\Throwable $sourceLookupError) {
                    Log::warning('CDN source lookup failed after import', [
                        'source_id' => $cdnSourceId,
                        'asset_id' => $cdnAssetId,
                        'error' => $sourceLookupError->getMessage(),
                    ]);
                }
            }

            if ($playback === null && $cdnAssetId) {
                try {
                    $manifestResponse = $cdnService->getPlaybackManifest($cdnAssetId);
                    if (($manifestResponse['ok'] ?? false) && is_array($manifestResponse['data']['playback'] ?? null)) {
                        $playback = $manifestResponse['data']['playback'];
                    }
                } catch (\Throwable $e) {
                    Log::debug('CDN playback manifest fetch failed after import', ['asset_id' => $cdnAssetId, 'error' => $e->getMessage()]);
                }
            }

            $downloadUrl = $cdnPublicUrl;
            $mp4PlayUrl = $playback['mp4_play_url'] ?? ($playback['mp4_url'] ?? null);
            $hlsMasterUrl = $playback['hls_master_url'] ?? null;
            if (($mp4PlayUrl === null || $hlsMasterUrl === null) && $downloadUrl) {
                $derivation = app(CdnUrlDerivationService::class)->deriveFromCdnUrl($downloadUrl);
                if ($derivation) {
                    if ($mp4PlayUrl === null) {
                        $mp4PlayUrl = $derivation['play_url'];
                    }
                    if ($hlsMasterUrl === null) {
                        $hlsMasterUrl = $derivation['hls_master_url'];
                    }
                }
            }

            if ($importMode === 'now' && $cdnStatus === 'failed') {
                return response()->json([
                    'success' => false,
                    'message' => is_string($cdnFailure) && $cdnFailure !== '' ? $cdnFailure : 'CDN import failed.',
                ], 422);
            }

            if ($existingSource) {
                $existingSource->update([
                    'file_path' => $cdnPublicUrl ?: $existingSource->file_path,
                    'quality' => $quality,
                    'format' => $format ?: $existingSource->format ?: 'mp4',
                    'file_size' => $cdnFileSize ?: $existingSource->file_size,
                    'is_active' => true,
                    'metadata' => array_merge((array) ($existingSource->metadata ?? []), [
                        'fetch_status' => $this->toFetchStatus($cdnStatus),
                        'fetch_mode' => $importMode,
                        'fetch_strategy' => $importStrategy,
                        'last_message' => is_string($cdnFailure) && $cdnFailure !== '' ? $cdnFailure : ('CDN import status: ' . strtoupper($cdnStatus)),
                        'cdn_asset_id' => $cdnAssetId,
                        'cdn_source_id' => $cdnSourceId,
                        'cdn_status' => $cdnStatus,
                        'last_synced_at' => now()->toDateTimeString(),
                    ]),
                ]);
                $videoSource = $existingSource;
            } else {
                $videoSource = VideoSource::create([
                    'sourceable_type' => $sourceableType,
                    'sourceable_id' => $sourceableId,
                    'type' => 'fetched',
                    'url' => $url,
                    'file_path' => $cdnPublicUrl,
                    'quality' => $quality,
                    'format' => $format ?: 'mp4',
                    'file_size' => $cdnFileSize,
                    'is_primary' => false,
                    'is_active' => true,
                    'metadata' => [
                        'fetch_status' => $this->toFetchStatus($cdnStatus),
                        'fetch_mode' => $importMode,
                        'fetch_strategy' => $importStrategy,
                        'last_message' => is_string($cdnFailure) && $cdnFailure !== '' ? $cdnFailure : ('CDN import status: ' . strtoupper($cdnStatus)),
                        'cdn_asset_id' => $cdnAssetId,
                        'cdn_source_id' => $cdnSourceId,
                        'cdn_status' => $cdnStatus,
                        'last_synced_at' => now()->toDateTimeString(),
                    ],
                ]);
            }

            $qualities = isset($playback['qualities']) && is_array($playback['qualities']) ? $playback['qualities'] : [];

            $this->ensureSiblingSourcesForFetch(
                $videoSource,
                $downloadUrl,
                $mp4PlayUrl,
                $hlsMasterUrl,
                $cdnAssetId,
                $cdnSourceId,
                $sourceableType,
                $sourceableId,
                $qualities
            );

            $message = $importMode === 'queue'
                ? 'Video fetch queued on CDN. Status will update shortly.'
                : ($cdnStatus === 'ready' ? 'Video fetched and saved successfully on CDN' : 'CDN import started.');

            return response()->json([
                'success' => true,
                'message' => $message,
                'video_source' => [
                    'id' => $videoSource->id,
                    'file_path' => $videoSource->file_path,
                    'file_size' => $videoSource->file_size,
                    'format' => $videoSource->format,
                    'quality' => $videoSource->quality,
                    'type' => $videoSource->type,
                    'status' => $cdnStatus,
                    'cdn_asset_id' => $cdnAssetId,
                    'cdn_source_id' => $cdnSourceId,
                    'import_strategy' => $importStrategy,
                ],
                'file_size' => $videoSource->file_size,
                'file_path' => $videoSource->file_path,
            ]);
        } catch (\Exception $e) {
            Log::error('Video fetch error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching video: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function toFetchStatus(string $cdnStatus): string
    {
        return match ($cdnStatus) {
            'ready' => 'completed',
            'failed' => 'failed',
            'processing', 'downloading' => 'processing',
            default => 'queued',
        };
    }

    private function isTlsConnectionReset(\Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'curl error 35')
            || str_contains($message, 'ssl_connect')
            || str_contains($message, 'connection reset by peer');
    }

    private function isCdnManagedUrl(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = (string) parse_url($url, PHP_URL_PATH);

        if ($host !== 'cdn.naraboxtv.com') {
            return false;
        }

        return str_starts_with($path, '/media/')
            || str_starts_with($path, '/media-hls/')
            || str_ends_with(strtolower($path), '.m3u8')
            || str_ends_with(strtolower($path), '.mp4');
    }

    private function normalizeRemoteUrl(string $url): string
    {
        $trimmed = trim($url);
        if ($trimmed === '') {
            return $trimmed;
        }

        $parts = parse_url($trimmed);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return $trimmed;
        }

        $path = (string) ($parts['path'] ?? '/');
        $normalizedPath = preg_replace('#/+#', '/', $path) ?: '/';

        $rebuilt = $parts['scheme'] . '://' . $parts['host'];
        if (isset($parts['port'])) {
            $rebuilt .= ':' . $parts['port'];
        }
        $rebuilt .= $normalizedPath;

        if (isset($parts['query']) && $parts['query'] !== '') {
            $rebuilt .= '?' . $parts['query'];
        }
        if (isset($parts['fragment']) && $parts['fragment'] !== '') {
            $rebuilt .= '#' . $parts['fragment'];
        }

        return $rebuilt;
    }

    private function resolveImportStrategy(string $url, string $requested): string
    {
        $normalizedRequested = strtolower(trim($requested));
        if ($normalizedRequested === 'python_worker') {
            return 'python_worker';
        }

        $enabled = (bool) config('services.cdn.python_worker_enabled', false);
        if (! $enabled) {
            return 'auto';
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $hosts = array_values(array_filter(array_map(
            static fn (string $item): string => strtolower(trim($item)),
            explode(',', (string) config('services.cdn.python_worker_hosts', 'mobifliks.info,mobifliks.com'))
        )));

        return in_array($host, $hosts, true) ? 'python_worker' : 'auto';
    }

    private function upsertDirectCdnUrlSource(
        string $url,
        string $sourceableType,
        int $sourceableId,
        string $quality,
        string $format,
        CdnMediaClientService $cdnService
    ): VideoSource {
        $parsed = parse_url($url);
        $path = (string) ($parsed['path'] ?? '');
        $segments = array_values(array_filter(explode('/', trim($path, '/'))));
        $isHlsMaster = str_ends_with(strtolower($path), '.m3u8');
        $isPlayMp4 = str_ends_with(strtolower($path), '_play.mp4');

        $cdnAssetId = null;
        $cdnSourceId = null;
        if (str_starts_with($path, '/media/')) {
            $cdnAssetId = $segments[1] ?? null; // /media/{assetId}/{sourceId}/{filename}
            $cdnSourceId = isset($segments[2]) && is_numeric($segments[2]) ? (int) $segments[2] : null;
        } elseif (str_starts_with($path, '/media-hls/')) {
            $cdnAssetId = $segments[1] ?? null; // /media-hls/{assetId}/{sourceId}/{path...}
            $cdnSourceId = isset($segments[2]) && is_numeric($segments[2]) ? (int) $segments[2] : null;
            $isHlsMaster = true;
        }

        $metadata = [
            'fetch_status' => 'completed',
            'fetch_mode' => 'direct_url',
            'last_message' => 'Direct CDN URL saved without CDN re-fetch.',
            'cdn_asset_id' => $cdnAssetId,
            'cdn_source_id' => $cdnSourceId,
            'cdn_status' => 'ready',
            'last_synced_at' => now()->toDateTimeString(),
            'download_url' => $this->toBrowserDownloadUrl($url),
            'playback_type' => $isHlsMaster ? 'hls' : 'mp4',
            'hls_master_url' => $isHlsMaster ? $url : null,
            'mp4_play_url' => $isPlayMp4 ? $url : null,
            'mp4_url' => $isPlayMp4 ? $url : null,
        ];

        if (is_string($cdnAssetId) && $cdnAssetId !== '') {
            try {
                $manifest = $cdnService->getPlaybackManifest($cdnAssetId);
                if (($manifest['ok'] ?? false) && is_array($manifest['data']['playback'] ?? null)) {
                    $playback = $manifest['data']['playback'];
                    $metadata['playback_type'] = $playback['type'] ?? 'mp4';
                    $metadata['hls_master_url'] = $playback['hls_master_url'] ?? null;
                    $metadata['mp4_play_url'] = $playback['mp4_play_url'] ?? ($playback['mp4_url'] ?? $url);
                    $metadata['mp4_url'] = $playback['mp4_url'] ?? $url;
                    $metadata['download_url'] = $playback['download_url'] ?? $metadata['download_url'];
                    $metadata['qualities'] = $playback['qualities'] ?? [];
                }
            } catch (\Throwable $e) {
                Log::warning('CDN playback manifest sync failed for direct URL source', [
                    'asset_id' => $cdnAssetId,
                    'source_id' => $cdnSourceId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $existingSource = VideoSource::where('sourceable_type', $sourceableType)
            ->where('sourceable_id', $sourceableId)
            ->where('url', $url)
            ->whereIn('type', ['url', 'fetched'])
            ->first();

        if ($existingSource) {
            $existingSource->update([
                'type' => 'url',
                'url' => $url,
                'file_path' => $url,
                'quality' => $quality !== '' ? $quality : ($existingSource->quality ?: 'auto'),
                'format' => $format !== '' && $format !== 'auto' ? $format : ($existingSource->format ?: 'mp4'),
                'is_active' => true,
                'metadata' => array_merge((array) ($existingSource->metadata ?? []), $metadata),
            ]);

            return $existingSource->fresh();
        }

        return VideoSource::create([
            'sourceable_type' => $sourceableType,
            'sourceable_id' => $sourceableId,
            'type' => 'url',
            'url' => $url,
            'file_path' => $url,
            'quality' => $quality !== '' ? $quality : 'auto',
            'format' => $format !== '' && $format !== 'auto' ? $format : 'mp4',
            'file_size' => null,
            'is_primary' => false,
            'is_active' => true,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Ensure sibling VideoSources exist for MP4 Playback and HLS Master URLs (same sourceable, same CDN asset/source).
     * Optionally create HLS variant sources from playback qualities.
     *
     * @param array<int, array{id?: string, label?: string, url?: string}> $qualities
     */
    private function ensureSiblingSourcesForFetch(
        VideoSource $primary,
        ?string $downloadUrl,
        ?string $mp4PlayUrl,
        ?string $hlsMasterUrl,
        $cdnAssetId,
        $cdnSourceId,
        string $sourceableType,
        int $sourceableId,
        array $qualities = []
    ): void {
        $baseMeta = [
            'cdn_asset_id' => $cdnAssetId,
            'cdn_source_id' => $cdnSourceId,
        ];

        if ($mp4PlayUrl !== null && $mp4PlayUrl !== '') {
            $existing = VideoSource::where('sourceable_type', $sourceableType)
                ->where('sourceable_id', $sourceableId)
                ->where(function ($q) use ($mp4PlayUrl, $cdnSourceId) {
                    $q->where('file_path', $mp4PlayUrl)
                        ->orWhere(function ($q2) use ($cdnSourceId) {
                            $q2->where('metadata->source_role', 'mp4_play')
                                ->where('metadata->cdn_source_id', $cdnSourceId);
                        });
                })
                ->first();

            if (! $existing) {
                $defaults = config('video_sources.defaults', []);
                VideoSource::create([
                    'sourceable_type' => $sourceableType,
                    'sourceable_id' => $sourceableId,
                    'type' => 'url',
                    'url' => $mp4PlayUrl,
                    'file_path' => $mp4PlayUrl,
                    'quality' => 'Optimized MP4',
                    'format' => 'mp4',
                    'file_size' => $defaults['file_size'] ?? null,
                    'duration_seconds' => $defaults['duration_seconds'] ?? null,
                    'is_primary' => $defaults['is_primary'] ?? false,
                    'is_active' => $defaults['is_active'] ?? true,
                    'metadata' => array_merge($baseMeta, ['source_role' => 'mp4_play']),
                ]);
            } else {
                $existing->update([
                    'file_path' => $mp4PlayUrl,
                    'url' => $mp4PlayUrl,
                    'metadata' => array_merge((array) ($existing->metadata ?? []), $baseMeta, ['source_role' => 'mp4_play']),
                ]);
            }
        }

        if ($hlsMasterUrl !== null && $hlsMasterUrl !== '') {
            $existing = VideoSource::where('sourceable_type', $sourceableType)
                ->where('sourceable_id', $sourceableId)
                ->where(function ($q) use ($hlsMasterUrl, $cdnSourceId) {
                    $q->where('file_path', $hlsMasterUrl)
                        ->orWhere(function ($q2) use ($cdnSourceId) {
                            $q2->where('metadata->source_role', 'hls_master')
                                ->where('metadata->cdn_source_id', $cdnSourceId);
                        });
                })
                ->first();

            if (! $existing) {
                $defaults = config('video_sources.defaults', []);
                VideoSource::create([
                    'sourceable_type' => $sourceableType,
                    'sourceable_id' => $sourceableId,
                    'type' => 'url',
                    'url' => $hlsMasterUrl,
                    'file_path' => $hlsMasterUrl,
                    'quality' => 'HLS',
                    'format' => 'm3u8',
                    'file_size' => $defaults['file_size'] ?? null,
                    'duration_seconds' => $defaults['duration_seconds'] ?? null,
                    'is_primary' => $defaults['is_primary'] ?? false,
                    'is_active' => $defaults['is_active'] ?? true,
                    'metadata' => array_merge($baseMeta, ['source_role' => 'hls_master']),
                ]);
            } else {
                $existing->update([
                    'file_path' => $hlsMasterUrl,
                    'url' => $hlsMasterUrl,
                    'metadata' => array_merge((array) ($existing->metadata ?? []), $baseMeta, ['source_role' => 'hls_master']),
                ]);
            }
        }

        $defaults = config('video_sources.defaults', []);

        foreach ($qualities as $q) {
            if (! is_array($q) || empty($q['url'])) {
                continue;
            }
            $variantUrl = $q['url'];
            $qualityId = $q['id'] ?? ('q-' . md5($variantUrl));
            $label = $q['label'] ?? $qualityId;

            $existing = VideoSource::where('sourceable_type', $sourceableType)
                ->where('sourceable_id', $sourceableId)
                ->where(function ($q2) use ($variantUrl, $qualityId, $cdnSourceId) {
                    $q2->where('file_path', $variantUrl)
                        ->orWhere(function ($q3) use ($qualityId, $cdnSourceId) {
                            $q3->where('metadata->source_role', 'hls_variant')
                                ->where('metadata->quality_id', $qualityId)
                                ->where('metadata->cdn_source_id', $cdnSourceId);
                        });
                })
                ->first();

            $variantMeta = array_merge($baseMeta, ['source_role' => 'hls_variant', 'quality_id' => $qualityId]);

            if (! $existing) {
                VideoSource::create([
                    'sourceable_type' => $sourceableType,
                    'sourceable_id' => $sourceableId,
                    'type' => 'url',
                    'url' => $variantUrl,
                    'file_path' => $variantUrl,
                    'quality' => $label,
                    'format' => 'm3u8',
                    'file_size' => $defaults['file_size'] ?? null,
                    'duration_seconds' => $defaults['duration_seconds'] ?? null,
                    'is_primary' => $defaults['is_primary'] ?? false,
                    'is_active' => $defaults['is_active'] ?? true,
                    'metadata' => $variantMeta,
                ]);
            } else {
                $existing->update([
                    'file_path' => $variantUrl,
                    'url' => $variantUrl,
                    'quality' => $label,
                    'metadata' => array_merge((array) ($existing->metadata ?? []), $variantMeta),
                ]);
            }
        }
    }

    private function toBrowserDownloadUrl(string $url): string
    {
        $parts = parse_url($url);
        if (! is_array($parts)) {
            return $url;
        }

        $query = [];
        parse_str((string) ($parts['query'] ?? ''), $query);
        $query['download'] = '1';

        $rebuilt = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '');
        if (isset($parts['port'])) {
            $rebuilt .= ':' . $parts['port'];
        }
        $rebuilt .= (string) ($parts['path'] ?? '');
        $rebuilt .= '?' . http_build_query($query);

        return $rebuilt;
    }
}
