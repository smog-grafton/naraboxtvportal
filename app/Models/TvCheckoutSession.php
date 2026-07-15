<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TvCheckoutSession extends Model
{
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_PENDING_PAYMENT = 'PENDING_PAYMENT';
    public const STATUS_COMPLETED = 'COMPLETED';
    public const STATUS_FAILED = 'FAILED';
    public const STATUS_EXPIRED = 'EXPIRED';

    protected $fillable = [
        'user_id',
        'tv_device_id',
        'uuid',
        'status',
        'type',
        'media_type',
        'media_id',
        'subscription_plan_id',
        'title',
        'amount',
        'transaction_ref',
        'expires_at',
        'last_viewed_at',
        'completed_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expires_at' => 'datetime',
            'last_viewed_at' => 'datetime',
            'completed_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(TvDevice::class, 'tv_device_id');
    }

    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }
}
