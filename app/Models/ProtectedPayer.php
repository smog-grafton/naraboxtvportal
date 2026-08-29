<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ProtectedPayer extends Model
{
    protected $fillable = [
        'normalized_phone', 'network', 'country', 'status', 'reason', 'notes',
        'created_by', 'expires_at', 'attempt_count', 'last_attempt_at',
    ];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'last_attempt_at' => 'datetime'];
    }

    public function scopeEffective(Builder $query): Builder
    {
        return $query->where('status', 'ACTIVE')
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }
}
