<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreatorWithdrawalRequest extends Model
{
    protected $fillable = [
        'user_id',
        'payout_method_id',
        'amount',
        'status',
        'reference',
        'requested_at',
        'approved_by',
        'approved_at',
        'processed_at',
        'failure_reason',
        'admin_notes',
        'gateway_used',
        'gateway_reference',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'requested_at' => 'datetime',
            'approved_at' => 'datetime',
            'processed_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public const STATUS_PENDING = 'pending';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payoutMethod(): BelongsTo
    {
        return $this->belongsTo(CreatorPayoutMethod::class, 'payout_method_id');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function payoutAttempts(): HasMany
    {
        return $this->hasMany(CreatorPayoutAttempt::class, 'withdrawal_request_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(CreatorWithdrawalAllocation::class, 'withdrawal_request_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isCancellable(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_UNDER_REVIEW]);
    }
}
