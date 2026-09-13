<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeletydeStorageEvent extends Model
{
    protected $fillable = [
        'event_id',
        'job_id',
        'video_source_id',
        'event_type',
        'status',
        'payload',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
