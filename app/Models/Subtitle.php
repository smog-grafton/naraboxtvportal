<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Subtitle extends Model
{
    protected $fillable = [
        'subtitleable_type',
        'subtitleable_id',
        'language',
        'label',
        'type',
        'file_path',
        'url',
        'format',
        'is_default',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function subtitleable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the full URL for the subtitle file
     */
    public function getFullUrlAttribute(): ?string
    {
        if ($this->type === 'upload' || $this->type === 'fetched') {
            return $this->file_path ? asset('storage/' . $this->file_path) : null;
        }
        
        return $this->url;
    }
}
