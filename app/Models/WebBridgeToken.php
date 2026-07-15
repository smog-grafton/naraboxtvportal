<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebBridgeToken extends Model
{
    protected $fillable = [
        'user_id',
        'token_hash',
        'next_path',
        'expires_at',
        'used_at',
        'issued_ip',
        'issued_user_agent',
        'consumed_ip',
        'consumed_user_agent',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

