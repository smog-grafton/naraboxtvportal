<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rental extends Model
{
    protected $fillable = [
        'user_id',
        'media_id',
        'rented_at',
        'expires_at',
        'amount',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rented_at' => 'datetime',
            'expires_at' => 'datetime',
            'amount' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        // Automatically expire rentals when accessed if they're past expires_at
        static::retrieved(function ($rental) {
            if ($rental->is_active && 
                $rental->expires_at && 
                $rental->expires_at->isPast()) {
                $rental->update(['is_active' => false]);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Movie::class, 'media_id');
    }

    public function isExpired(): bool
    {
        if (!$this->expires_at) {
            return false;
        }
        
        // If expired, update is_active
        if ($this->expires_at->isPast() && $this->is_active) {
            $this->update(['is_active' => false]);
            return true;
        }
        
        return $this->expires_at->isPast();
    }
}
