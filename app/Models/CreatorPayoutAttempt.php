<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreatorPayoutAttempt extends Model
{
    protected $fillable = [
        'withdrawal_request_id',
        'gateway',
        'gateway_request',
        'gateway_response',
        'status',
        'external_id',
        'attempted_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'gateway_request' => 'array',
            'gateway_response' => 'array',
            'attempted_at' => 'datetime',
        ];
    }

    public function withdrawalRequest(): BelongsTo
    {
        return $this->belongsTo(CreatorWithdrawalRequest::class, 'withdrawal_request_id');
    }
}
