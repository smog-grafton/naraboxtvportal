<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    protected $fillable = [
        'user_id',
        'plan',
        'status',
        'start_date',
        'end_date',
        'renewal_date',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'renewal_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        // Automatically expire subscriptions when accessed if they're past end_date
        static::retrieved(function ($subscription) {
            if (strtoupper($subscription->status) === 'ACTIVE' && 
                $subscription->end_date && 
                $subscription->end_date->isPast()) {
                $subscription->update(['status' => 'EXPIRED']);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        // Check if expired and update if needed
        if (strtoupper($this->status) === 'ACTIVE' && 
            $this->end_date && 
            $this->end_date->isPast()) {
            $this->update(['status' => 'EXPIRED']);
            return false;
        }
        
        return strtoupper($this->status) === 'ACTIVE' && 
               ($this->end_date === null || $this->end_date->isFuture());
    }
}
