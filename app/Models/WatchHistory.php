<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\TVShow;

class WatchHistory extends Model
{
    protected $table = 'watch_history';

    protected $fillable = [
        'user_id',
        'media_id',
        'media_type',
        'episode_id',
        'progress_seconds',
        'total_seconds',
        'last_watched_at',
    ];

    protected function casts(): array
    {
        return [
            'last_watched_at' => 'datetime',
            'media_type' => 'string',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Movie::class, 'media_id');
    }

    public function tvShow(): BelongsTo
    {
        return $this->belongsTo(TVShow::class, 'media_id');
    }

    public function episode(): BelongsTo
    {
        return $this->belongsTo(Episode::class);
    }
}
