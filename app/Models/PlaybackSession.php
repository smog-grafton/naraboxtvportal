<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlaybackSession extends Model
{
    protected $fillable = [
        'session_uuid',
        'user_id',
        'media_id',
        'media_type',
        'episode_id',
        'device_type',
        'preferences',
        'quality_history',
        'error_log',
        'started_at',
        'last_ping_at',
        'ended_at',
        'exit_reason',
        'startup_ms',
        'total_watch_seconds',
        'max_position_seconds',
        'buffer_count',
        'total_buffer_ms',
        'quality_switch_count',
        'error_count',
        'last_quality',
    ];

    protected function casts(): array
    {
        return [
            'preferences' => 'array',
            'quality_history' => 'array',
            'error_log' => 'array',
            'started_at' => 'datetime',
            'last_ping_at' => 'datetime',
            'ended_at' => 'datetime',
            'startup_ms' => 'integer',
            'total_watch_seconds' => 'integer',
            'max_position_seconds' => 'float',
            'buffer_count' => 'integer',
            'total_buffer_ms' => 'integer',
            'quality_switch_count' => 'integer',
            'error_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function episode(): BelongsTo
    {
        return $this->belongsTo(Episode::class);
    }
}
