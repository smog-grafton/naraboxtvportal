<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class AdBanner extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'type',
        'image_path',
        'script_content',
        'target_url',
        'width',
        'height',
        'placement',
        'platform',
        'is_active',
        'active_from',
        'active_until',
        'sort_order',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'active_from' => 'datetime',
        'active_until' => 'datetime',
    ];

    public function scopeActive(Builder $query): Builder
    {
        $now = Carbon::now();

        return $query
            ->where('is_active', true)
            ->where(function (Builder $q) use ($now) {
                $q->whereNull('active_from')
                    ->orWhere('active_from', '<=', $now);
            })
            ->where(function (Builder $q) use ($now) {
                $q->whereNull('active_until')
                    ->orWhere('active_until', '>=', $now);
            });
    }
}

