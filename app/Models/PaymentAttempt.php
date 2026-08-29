<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAttempt extends Model
{
    protected $fillable = [
        'user_id', 'provider', 'provider_user_id', 'ip_address', 'device_id',
        'payer_phone', 'gateway', 'payment_type', 'allowed', 'decision_code',
        'risk_level', 'risk_reasons', 'metadata', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'allowed' => 'boolean', 'risk_reasons' => 'array', 'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
