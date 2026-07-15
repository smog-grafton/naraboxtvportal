<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Builder;

class CreatorEarning extends Model
{
    protected $fillable = [
        'user_id',
        'transaction_id',
        'earnable_type',
        'earnable_id',
        'gross_amount',
        'commission_rate',
        'platform_amount',
        'creator_amount',
        'status',
        'available_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'gross_amount' => 'decimal:2',
            'commission_rate' => 'decimal:2',
            'platform_amount' => 'decimal:2',
            'creator_amount' => 'decimal:2',
            'available_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class, 'transaction_id');
    }

    public function earnable(): MorphTo
    {
        return $this->morphTo();
    }

    public function withdrawalAllocations(): HasMany
    {
        return $this->hasMany(CreatorWithdrawalAllocation::class, 'creator_earning_id');
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', 'available');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }
}
