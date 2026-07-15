<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerPreference extends Model
{
    protected $fillable = [
        'user_id',
        'autoplay_next_episode',
        'preferred_subtitle',
        'preferred_subtitle_enabled',
        'preferred_quality',
        'volume',
        'muted',
        'theater_mode',
        'keyboard_shortcuts_enabled',
    ];

    protected function casts(): array
    {
        return [
            'autoplay_next_episode' => 'boolean',
            'preferred_subtitle_enabled' => 'boolean',
            'volume' => 'float',
            'muted' => 'boolean',
            'theater_mode' => 'boolean',
            'keyboard_shortcuts_enabled' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
