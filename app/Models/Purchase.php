<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Purchase extends Model
{
    protected $fillable = [
        'user_id',
        'media_id',
        'purchased_at',
        'amount',
        'download_enabled',
    ];

    protected function casts(): array
    {
        return [
            'purchased_at' => 'datetime',
            'amount' => 'decimal:2',
            'download_enabled' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Movie::class, 'media_id');
    }
}
