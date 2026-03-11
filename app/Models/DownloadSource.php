<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class DownloadSource extends Model
{
    protected $fillable = [
        'downloadable_type',
        'downloadable_id',
        'type',
        'url',
        'file_path',
        'quality',
        'format',
        'file_size',
        'label',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function downloadable(): MorphTo
    {
        return $this->morphTo();
    }

    public function getDownloadUrlAttribute(): ?string
    {
        if ($this->type === 'local' || $this->type === 'fetched') {
            if (! $this->file_path) {
                return null;
            }

            if (str_starts_with($this->file_path, 'http://') || str_starts_with($this->file_path, 'https://')) {
                return $this->file_path;
            }

            return asset('storage/' . ltrim($this->file_path, '/'));
        }
        
        return $this->url;
    }
}
