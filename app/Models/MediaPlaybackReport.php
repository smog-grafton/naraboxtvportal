<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class MediaPlaybackReport extends Model
{
    protected $fillable = [
        'user_id',
        'playback_session_id',
        'source_id',
        'media_type',
        'media_id',
        'episode_id',
        'error_type',
        'error_message',
        'playback_url',
        'device',
        'app_version',
        'load_time_ms',
        'buffering_count',
        'buffering_duration_ms',
        'report_count',
        'status',
        'needs_attention',
        'is_slow',
        'admin_notes',
    ];

    protected function casts(): array
    {
        return [
            'load_time_ms' => 'integer',
            'buffering_count' => 'integer',
            'buffering_duration_ms' => 'integer',
            'report_count' => 'integer',
            'needs_attention' => 'boolean',
            'is_slow' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function playbackSession(): BelongsTo
    {
        return $this->belongsTo(PlaybackSession::class);
    }

    public function movie(): BelongsTo
    {
        return $this->belongsTo(Movie::class, 'media_id');
    }

    public function tvShow(): BelongsTo
    {
        return $this->belongsTo(TVShow::class, 'media_id');
    }

    public function episode(): BelongsTo
    {
        return $this->belongsTo(Episode::class, 'episode_id');
    }

    public function season(): HasOneThrough
    {
        return $this->hasOneThrough(
            Season::class,
            Episode::class,
            'id',
            'id',
            'episode_id',
            'season_id',
        );
    }

    public function getResolvedContentTitleAttribute(): string
    {
        if ($this->episode) {
            $showTitle = $this->episode->season?->tvShow?->title;
            $seasonNumber = $this->episode->season?->number;
            $episodeNumber = $this->episode->number;

            $prefix = $showTitle ? $showTitle . ' - ' : '';
            $episodeCode = $seasonNumber && $episodeNumber
                ? sprintf('S%sE%s', $seasonNumber, $episodeNumber)
                : ($episodeNumber ? 'Episode ' . $episodeNumber : 'Episode');

            return trim($prefix . $episodeCode . ': ' . $this->episode->title);
        }

        if (strtoupper((string) $this->media_type) === 'TV_SHOW' && $this->tvShow) {
            return $this->tvShow->title;
        }

        if ($this->movie) {
            return $this->movie->title;
        }

        return 'Unknown content #' . ($this->episode_id ?: $this->media_id);
    }

    public function getResolvedContentSubtitleAttribute(): ?string
    {
        if ($this->episode?->season?->tvShow?->title) {
            return 'TV Show Episode';
        }

        return match (strtoupper((string) $this->media_type)) {
            'TV_SHOW' => 'TV Show',
            'EPISODE' => 'Episode',
            default => 'Movie',
        };
    }
}
