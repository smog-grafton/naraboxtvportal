<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerAttribution extends Model
{
    protected $fillable = [
        'user_id', 'partner_id', 'campaign_id', 'attribution_method',
        'referral_code', 'attributed_at', 'commission_valid_from',
        'commission_valid_until', 'locked_at', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'attributed_at' => 'datetime',
            'commission_valid_from' => 'datetime',
            'commission_valid_until' => 'datetime',
            'locked_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(PartnerCampaign::class, 'campaign_id');
    }
}
