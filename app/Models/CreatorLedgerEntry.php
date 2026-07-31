<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreatorLedgerEntry extends Model
{
    protected $fillable = [
        'wallet_id', 'user_id', 'amount_minor', 'currency', 'entry_type',
        'bucket', 'status', 'reference_type', 'reference_id',
        'idempotency_key', 'description', 'metadata', 'available_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'available_at' => 'datetime'];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(CreatorWallet::class);
    }
}
