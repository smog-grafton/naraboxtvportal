<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PlaybackMarker extends Model
{
    protected $fillable = [
        'markerable_type',
        'markerable_id',
        'marker_type',
        'start_seconds',
        'end_seconds',
        'label',
        'description',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'start_seconds' => 'float',
            'end_seconds' => 'float',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function markerable(): MorphTo
    {
        return $this->morphTo();
    }
}
