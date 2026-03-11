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
     * Only syncs url, fetched, and local types (not youtube/vimeo)
     */
    public function syncToDownloadSource()
    {
        // Only sync downloadable types
        if (!in_array($this->type, ['url', 'fetched', 'local'])) {
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

        // Check if download source already exists for this video source
        // Use a unique identifier based on video source ID
        $existingDownloadSource = DownloadSource::where('downloadable_type', $downloadableType)
            ->where('downloadable_id', $downloadableId)
            ->where('type', $this->type)
            ->where(function($query) {
                // Match by URL for url/fetched types, or file_path for local/fetched
                if ($this->type === 'url' && $this->url) {
                    $query->where('url', $this->url);
                } elseif (in_array($this->type, ['fetched', 'local']) && $this->file_path) {
                    $query->where('file_path', $this->file_path);
                } else {
                    // Fallback: match by quality and format
                    $query->where('quality', $this->quality ?? 'auto')
                          ->where('format', $this->format ?? 'mp4');
                }
            })
            ->first();

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

        $downloadData = [
            'downloadable_type' => $downloadableType,
            'downloadable_id' => $downloadableId,
            'type' => $this->type,
            'url' => $this->type === 'url' ? $this->url : null,
            'file_path' => in_array($this->type, ['fetched', 'local']) ? $this->file_path : null,
            'quality' => $quality,
            'format' => $format,
            'file_size' => $this->file_size,
            'label' => $quality . ' ' . strtoupper($format),
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
                if ($this->type === 'url' && $this->url) {
                    $query->where('url', $this->url);
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
        if ($this->type === 'local' || $this->type === 'fetched') {
            if (! $this->file_path) {
                return null;
            }

            if (str_starts_with($this->file_path, 'http://') || str_starts_with($this->file_path, 'https://')) {
                return $this->file_path;
            }

            return asset('storage/' . ltrim($this->file_path, '/'));
        }
        
        return $this->url;
    }
}
