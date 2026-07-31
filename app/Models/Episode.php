<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use App\Models\PlaybackMarker;
use App\Models\Subtitle;

class Episode extends Model
{
    protected $fillable = [
        'season_id',
        'number',
        'title',
        'thumbnail',
        'duration',
        'description',
        'video_url',
        'download_enabled',
        'submitted_by',
        'creator_application_id',
        'submission_origin',
        'processing_status',
        'editorial_status',
        'publication_status',
        'monetization_status',
        'submitted_for_review_at',
        'approved_by',
        'approved_at',
        'published_by',
        'published_at',
        'scheduled_for',
        'moderation_notes',
        'release_date',
        'is_active',
        'translation_language',
        'short_description',
        'tags',
    ];

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    protected function casts(): array
    {
        return [
            'download_enabled' => 'boolean',
            'submitted_for_review_at' => 'datetime',
            'approved_at' => 'datetime',
            'published_at' => 'datetime',
            'scheduled_for' => 'datetime',
            'release_date' => 'date',
            'is_active' => 'boolean',
            'tags' => 'array',
        ];
    }

    public function watchHistory()
    {
        return $this->hasMany(WatchHistory::class);
    }

    public function videoSources(): MorphMany
    {
        return $this->morphMany(VideoSource::class, 'sourceable');
    }

    public function submittingUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function creatorSubmissions(): MorphMany
    {
        return $this->morphMany(CreatorContentSubmission::class, 'content');
    }

    public function creatorReviews(): MorphMany
    {
        return $this->morphMany(CreatorContentReview::class, 'content');
    }

    public function monetizationSetting()
    {
        return $this->morphOne(CreatorMonetizationSetting::class, 'content');
    }

    public function downloadSources(): MorphMany
    {
        return $this->morphMany(DownloadSource::class, 'downloadable');
    }

    public function subtitles(): MorphMany
    {
        return $this->morphMany(Subtitle::class, 'subtitleable');
    }

    public function playbackMarkers(): MorphMany
    {
        return $this->morphMany(PlaybackMarker::class, 'markerable');
    }
}
