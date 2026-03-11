<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
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
    ];

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    protected function casts(): array
    {
        return [
            'download_enabled' => 'boolean',
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

    public function downloadSources(): MorphMany
    {
        return $this->morphMany(DownloadSource::class, 'downloadable');
    }

    public function subtitles(): MorphMany
    {
        return $this->morphMany(Subtitle::class, 'subtitleable');
    }
}
