<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiveStream extends Model
{
    protected $fillable = [
        'title',
        'description',
        'stream_url',
        'platform',
        'is_live',
        'is_archived',
        'thumbnail',
        'viewer_count',
        'order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_live' => 'boolean',
            'is_archived' => 'boolean',
            'is_active' => 'boolean',
            'viewer_count' => 'integer',
            'order' => 'integer',
        ];
    }
}
