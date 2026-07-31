<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreatorWallet extends Model
{
    protected $fillable = ['user_id', 'currency', 'status', 'hold_reason', 'held_at', 'held_by'];

    protected function casts(): array
    {
        return ['held_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(CreatorLedgerEntry::class, 'wallet_id');
    }

    public function holder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'held_by');
    }
}
