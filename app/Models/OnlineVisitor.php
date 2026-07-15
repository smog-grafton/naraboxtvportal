<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class OnlineVisitor extends Model
{
    protected $fillable = [
        'visitor_key',
        'user_id',
        'platform',
        'guest_id',
        'ip_address',
        'user_agent',
        'last_path',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query, Carbon $cutoff): Builder
    {
        return $query->where('last_seen_at', '>=', $cutoff);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
