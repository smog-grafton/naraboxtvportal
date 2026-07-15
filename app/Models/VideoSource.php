<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use App\Models\DownloadSource;

class VideoSource extends Model
{
    protected $fillable = [
        'sourceable_type',
        'sourceable_id',
        'type',
        'url',
        'file_path',
        'quality',
        'format',
        'file_size',
        'duration_seconds',
        'is_primary',
        'is_active',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'duration_seconds' => 'integer',
            'is_primary' => 'boolean',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        // Automatically sync to download sources when video source is created or updated
        static::created(function ($videoSource) {
            $videoSource->syncToDownloadSource();
            app(\App\Services\VideoSourceDerivationService::class)->ensureDerivedSourcesForCdnUrl($videoSource);
        });

        static::updated(function ($videoSource) {
            $videoSource->syncToDownloadSource();
            app(\App\Services\VideoSourceDerivationService::class)->ensureDerivedSourcesForCdnUrl($videoSource);
        });

        // Delete corresponding download source when video source is deleted
        static::deleted(function ($videoSource) {
            $videoSource->deleteDownloadSource();
        });
    }

    /**
     * Sync this video source to a download source
     * Only syncs downloadable source types (not youtube/vimeo)
     */
    public function syncToDownloadSource()
    {
        // Only sync downloadable types
        if (! $this->isDownloadableSourceType()) {
            return;
        }

        // Only sync if active
        if (!$this->is_active) {
            // If video source is inactive, deactivate corresponding download source
            $this->deleteDownloadSource();
            return;
        }

        // Map sourceable to downloadable (same structure)
        $downloadableType = $this->sourceable_type;
        $downloadableId = $this->sourceable_id;

        $downloadUrl = $this->downloadableUrl();

        // Check if download source already exists for this video source
        // Use a unique identifier based on video source ID
        $existingDownloadQuery = DownloadSource::where('downloadable_type', $downloadableType)
            ->where('downloadable_id', $downloadableId)
            ->where('type', $this->type);

        if ($this->type === 'bunny_stream') {
            $existingDownloadSource = $existingDownloadQuery->first();
        } else {
            $existingDownloadSource = $existingDownloadQuery->where(function($query) use ($downloadUrl) {
                // Match by URL for url/fetched types, or file_path for local/fetched
                if ($this->usesDownloadUrlColumn() && $downloadUrl) {
                    $query->where('url', $downloadUrl);
                } elseif (in_array($this->type, ['fetched', 'local']) && $this->file_path) {
                    $query->where('file_path', $this->file_path);
                } else {
                    // Fallback: match by quality and format
                    $query->where('quality', $this->quality ?? 'auto')
                          ->where('format', $this->format ?? 'mp4');
                }
            })
            ->first();
        }

        // Determine quality and format
        $quality = $this->quality;
        if (empty($quality) || $quality === 'auto') {
            // Try to infer quality from file_path or URL if available
            $quality = 'auto';
        }

        $format = $this->format;
        if (empty($format) || $format === 'auto') {
            // Try to extract format from file_path or URL
            if ($this->file_path) {
                $pathInfo = pathinfo($this->file_path);
                $format = $pathInfo['extension'] ?? 'mp4';
            } elseif ($this->url) {
                $parsedUrl = parse_url($this->url);
                $path = $parsedUrl['path'] ?? '';
                if (preg_match('/\.([a-z0-9]+)$/i', $path, $matches)) {
                    $format = strtolower($matches[1]);
                } else {
                    $format = 'mp4';
                }
            } else {
                $format = 'mp4';
            }
        }

        $downloadFormat = $this->downloadableFormat($downloadUrl, $format);

        if (! $downloadUrl || $this->isHlsDownloadCandidate($downloadUrl, $downloadFormat)) {
            $this->deleteDownloadSource();
            return;
        }

        $downloadData = [
            'downloadable_type' => $downloadableType,
            'downloadable_id' => $downloadableId,
            'type' => $this->type,
            'url' => $this->usesDownloadUrlColumn() || ! $this->file_path ? $downloadUrl : null,
            'file_path' => in_array($this->type, ['fetched', 'local'], true) && $this->file_path ? $this->file_path : null,
            'quality' => $quality,
            'format' => $downloadFormat,
            'file_size' => $this->file_size,
            'label' => $quality . ' ' . strtoupper($downloadFormat),
            'is_active' => $this->is_active,
        ];

        if ($existingDownloadSource) {
            // Update existing download source
            $existingDownloadSource->update($downloadData);
        } else {
            // Create new download source
            DownloadSource::create($downloadData);
        }
    }

    /**
     * Delete corresponding download source
     */
    public function deleteDownloadSource()
    {
        $downloadableType = $this->sourceable_type;
        $downloadableId = $this->sourceable_id;

        DownloadSource::where('downloadable_type', $downloadableType)
            ->where('downloadable_id', $downloadableId)
            ->where('type', $this->type)
            ->where(function($query) {
                if ($this->type === 'bunny_stream') {
                    $query->where('type', 'bunny_stream');
                } elseif ($this->usesDownloadUrlColumn() && $this->downloadableUrl()) {
                    $query->where('url', $this->downloadableUrl());
                } elseif (in_array($this->type, ['fetched', 'local']) && $this->file_path) {
                    $query->where('file_path', $this->file_path);
                }
            })
            ->delete();
    }

    public function sourceable(): MorphTo
    {
        return $this->morphTo();
    }

    public function getFullUrlAttribute(): ?string
    {
        if ($url = $this->metadataBackedUrl(includeHls: true)) {
            return $url;
        }

        if (in_array($this->type, ['contabo_object_storage', 'tele_ob'], true)) {
            return $this->downloadableUrl();
        }

        if ($this->type === 'local' || $this->type === 'fetched') {
            $path = $this->file_path ?: $this->url;
            if (! $path) {
                return $this->url ?: null;
            }

            if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                return $path;
            }

            return asset('storage/' . ltrim($path, '/'));
        }
        
        return $this->url ?: $this->file_path;
    }

    private function downloadableUrl(): ?string
    {
        if ($url = $this->metadataBackedUrl(includeHls: false)) {
            return $url;
        }

        if (in_array($this->type, ['contabo_object_storage', 'tele_ob'], true)) {
            $metadata = is_array($this->metadata) ? $this->metadata : [];

            foreach ([
                $metadata['public_url'] ?? null,
                $metadata['download_url'] ?? null,
                $metadata['mp4_url'] ?? null,
                $this->url,
                $this->file_path,
            ] as $url) {
                if (is_string($url) && trim($url) !== '') {
                    return trim($url);
                }
            }

            return null;
        }

        if ($this->type !== 'bunny_stream') {
            return $this->url ?: $this->file_path;
        }

        $metadata = is_array($this->metadata) ? $this->metadata : [];
        $playback = is_array($metadata['bunny_stream_playback'] ?? null) ? $metadata['bunny_stream_playback'] : [];

        foreach ([
            $playback['download_url'] ?? null,
            $metadata['download_url'] ?? null,
            $playback['original_url'] ?? null,
            $metadata['original_url'] ?? null,
            $playback['mp4_url'] ?? null,
            $metadata['mp4_url'] ?? null,
            $playback['mp4_play_url'] ?? null,
            $metadata['mp4_play_url'] ?? null,
            $metadata['source_url'] ?? null,
        ] as $url) {
            if (is_string($url) && trim($url) !== '') {
                return trim($url);
            }
        }

        return null;
    }

    private function metadataBackedUrl(bool $includeHls): ?string
    {
        $metadata = is_array($this->metadata) ? $this->metadata : [];

        $candidates = [
            $metadata['mp4_play_url'] ?? null,
            $metadata['mp4_url'] ?? null,
            $metadata['download_mp4_url'] ?? null,
            $metadata['download_url'] ?? null,
            $metadata['original_url'] ?? null,
            $metadata['public_url'] ?? null,
            $metadata['source_url'] ?? null,
        ];

        if ($includeHls) {
            $candidates[] = $metadata['hls_master_url'] ?? null;
            $candidates[] = $metadata['hls_url'] ?? null;
        }

        foreach ($candidates as $url) {
            if (! is_string($url) || trim($url) === '') {
                continue;
            }

            $url = trim($url);
            if (! $includeHls && $this->isHlsDownloadCandidate($url, null)) {
                continue;
            }

            return $url;
        }

        return null;
    }

    private function isDownloadableSourceType(): bool
    {
        return in_array($this->type, [
            'url',
            'direct',
            'upload',
            'uploaded',
            'local',
            'fetched',
            'curl',
            'cdn',
            'legacy_cdn',
            'contabo',
            'contabo_object_storage',
            'tele_ob',
            'bunny_stream',
            'nbx-engine',
        ], true);
    }

    private function usesDownloadUrlColumn(): bool
    {
        return in_array($this->type, [
            'url',
            'direct',
            'upload',
            'uploaded',
            'cdn',
            'legacy_cdn',
            'contabo',
            'contabo_object_storage',
            'tele_ob',
            'bunny_stream',
            'nbx-engine',
        ], true);
    }

    private function downloadableFormat(?string $downloadUrl, ?string $fallback): string
    {
        if (in_array($this->type, ['bunny_stream', 'nbx-engine'], true)) {
            return 'mp4';
        }

        if ($fallback && $fallback !== 'auto') {
            return $fallback;
        }

        $path = is_string($downloadUrl) ? parse_url($downloadUrl, PHP_URL_PATH) : null;
        $extension = is_string($path) ? pathinfo($path, PATHINFO_EXTENSION) : '';

        return $extension !== '' ? strtolower($extension) : 'mp4';
    }

    private function isHlsDownloadCandidate(?string $url, ?string $format): bool
    {
        if (strtolower((string) $format) === 'm3u8') {
            return true;
        }

        $path = is_string($url) ? strtolower((string) parse_url($url, PHP_URL_PATH)) : '';

        return str_ends_with($path, '.m3u8');
    }
}
