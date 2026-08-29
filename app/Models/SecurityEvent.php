<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityEvent extends Model
{
    protected $fillable = [
        'event_type', 'risk_level', 'user_id', 'partner_id', 'security_rule_id',
        'security_identity_id', 'payment_transaction_id', 'provider',
        'provider_user_id', 'ip_address', 'device_id', 'payer_phone', 'route',
        'action', 'reason', 'metadata', 'actor_user_id', 'occurred_at',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'occurred_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
