<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Trailer extends Model
{
    protected $fillable = [
        'trailerable_type',
        'trailerable_id',
        'tmdb_id',
        'key',
        'name',
        'site',
        'type',
        'size',
        'is_primary',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'is_primary' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function trailerable(): MorphTo
    {
        return $this->morphTo();
    }

    public function getYoutubeUrlAttribute(): string
    {
        return 'https://www.youtube.com/watch?v=' . $this->key;
    }

    public function getEmbedUrlAttribute(): string
    {
        return 'https://www.youtube.com/embed/' . $this->key;
    }
}
