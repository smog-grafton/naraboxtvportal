<?php

namespace App\Services;

use App\Models\VideoSource;

class VideoSourceDerivationService
{
    public function __construct(
        protected CdnUrlDerivationService $cdnUrlDerivation
    ) {}

    /**
     * When a VideoSource is created/updated with a CDN URL, ensure sibling sources exist
     * (MP4 Playback and HLS Master, or Original if the user pasted play/HLS URL).
     * Skips if the source is already a derived sibling (has source_role in metadata).
     */
    public function ensureDerivedSourcesForCdnUrl(VideoSource $source): void
    {
        $metadata = (array) ($source->metadata ?? []);
        if (isset($metadata['source_role']) && in_array($metadata['source_role'], ['mp4_play', 'hls_master', 'original'], true)) {
            return;
        }

        $currentUrl = $source->file_path ?: $source->url;
        if (! is_string($currentUrl) || $currentUrl === '') {
            return;
        }

        $derivation = $this->cdnUrlDerivation->deriveFromCdnUrl($currentUrl);
        if ($derivation === null) {
            return;
        }

        $sourceableType = $source->sourceable_type;
        $sourceableId = $source->sourceable_id;
        $uuid = $derivation['uuid'];
        $sourceId = $derivation['source_id'];
        $baseMeta = [
            'cdn_asset_id' => $uuid,
            'cdn_source_id' => $sourceId,
        ];

        $normalize = static fn (?string $u) => $u === null ? '' : rtrim($u, '/');
        $current = $normalize($currentUrl);

        $defaultQuality = config('video_sources.defaults.quality', '480p');

        if ($derivation['play_url'] !== null && $normalize($derivation['play_url']) !== $current) {
            $this->ensureSibling($sourceableType, $sourceableId, $derivation['play_url'], 'Play 480p', 'mp4', array_merge($baseMeta, ['source_role' => 'mp4_play']));
        }

        if ($derivation['hls_master_url'] !== null && $normalize($derivation['hls_master_url']) !== $current) {
            $this->ensureSibling($sourceableType, $sourceableId, $derivation['hls_master_url'], 'hls 480p', 'm3u8', array_merge($baseMeta, ['source_role' => 'hls_master']));
        }

        if ($derivation['download_url'] !== null && $normalize($derivation['download_url']) !== $current) {
            $this->ensureSibling($sourceableType, $sourceableId, $derivation['download_url'], $defaultQuality, 'mp4', array_merge($baseMeta, ['source_role' => 'original']));
        }
    }

    private function ensureSibling(
        string $sourceableType,
        int $sourceableId,
        string $url,
        string $quality,
        string $format,
        array $metadata
    ): void {
        $existing = VideoSource::where('sourceable_type', $sourceableType)
            ->where('sourceable_id', $sourceableId)
            ->where(function ($q) use ($url, $metadata) {
                $q->where('file_path', $url)
                    ->orWhere('url', $url)
                    ->orWhere(function ($q2) use ($metadata) {
                        $q2->where('metadata->source_role', $metadata['source_role'] ?? '')
                            ->where('metadata->cdn_source_id', $metadata['cdn_source_id'] ?? null);
                    });
            })
            ->first();

        if ($existing) {
            $existing->update([
                'file_path' => $url,
                'url' => $url,
                'quality' => $quality,
                'format' => $format,
                'metadata' => array_merge((array) ($existing->metadata ?? []), $metadata),
            ]);

            return;
        }

        $defaults = config('video_sources.defaults', []);

        VideoSource::create([
            'sourceable_type' => $sourceableType,
            'sourceable_id' => $sourceableId,
            'type' => 'url',
            'url' => $url,
            'file_path' => $url,
            'quality' => $quality,
            'format' => $format,
            'file_size' => $defaults['file_size'] ?? null,
            'duration_seconds' => $defaults['duration_seconds'] ?? null,
            'is_primary' => $defaults['is_primary'] ?? false,
            'is_active' => $defaults['is_active'] ?? true,
            'metadata' => $metadata,
        ]);
    }
}
