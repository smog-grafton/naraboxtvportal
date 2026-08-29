<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartnerCampaign extends Model
{
    protected $fillable = [
        'partner_id', 'name', 'slug', 'referral_code', 'commission_bps',
        'status', 'starts_at', 'ends_at', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'commission_bps' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function attributions(): HasMany
    {
        return $this->hasMany(PartnerAttribution::class, 'campaign_id');
    }

    public function referralEvents(): HasMany
    {
        return $this->hasMany(PartnerReferralEvent::class, 'campaign_id');
    }

    public function isActiveAt(?\DateTimeInterface $at = null): bool
    {
        $at = $at ? now()->setTimestamp($at->getTimestamp()) : now();

        return $this->status === 'active'
            && (! $this->starts_at || $this->starts_at->lte($at))
            && (! $this->ends_at || $this->ends_at->gte($at));
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
