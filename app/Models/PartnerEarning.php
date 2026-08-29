<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerEarning extends Model
{
    protected $fillable = [
        'partner_id', 'user_id', 'transaction_id', 'campaign_id', 'creator_id',
        'gross_amount', 'commissionable_amount', 'creator_amount',
        'platform_share_before_partner', 'partner_rate', 'partner_amount',
        'platform_final_amount', 'gross_amount_minor', 'commissionable_amount_minor',
        'creator_amount_minor', 'platform_share_before_partner_minor', 'partner_rate_bps',
        'partner_amount_minor', 'platform_final_amount_minor', 'status',
        'idempotency_key', 'earned_at', 'available_at', 'paid_at', 'reversed_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'gross_amount' => 'decimal:2',
            'commissionable_amount' => 'decimal:2',
            'creator_amount' => 'decimal:2',
            'platform_share_before_partner' => 'decimal:2',
            'partner_rate' => 'decimal:2',
            'partner_amount' => 'decimal:2',
            'platform_final_amount' => 'decimal:2',
            'gross_amount_minor' => 'integer',
            'commissionable_amount_minor' => 'integer',
            'creator_amount_minor' => 'integer',
            'platform_share_before_partner_minor' => 'integer',
            'partner_rate_bps' => 'integer',
            'partner_amount_minor' => 'integer',
            'platform_final_amount_minor' => 'integer',
            'earned_at' => 'datetime',
            'available_at' => 'datetime',
            'paid_at' => 'datetime',
            'reversed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class, 'transaction_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(PartnerCampaign::class, 'campaign_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', 'available');
    }
}
