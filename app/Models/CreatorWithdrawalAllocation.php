<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreatorWithdrawalAllocation extends Model
{
    protected $fillable = [
        'withdrawal_request_id',
        'creator_earning_id',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function withdrawalRequest(): BelongsTo
    {
        return $this->belongsTo(CreatorWithdrawalRequest::class, 'withdrawal_request_id');
    }

    public function creatorEarning(): BelongsTo
    {
        return $this->belongsTo(CreatorEarning::class, 'creator_earning_id');
    }
}
