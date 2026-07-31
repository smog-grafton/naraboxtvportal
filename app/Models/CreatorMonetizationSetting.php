<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreatorMonetizationSetting extends Model
{
    protected $fillable = [
        'content_type', 'content_id', 'user_id', 'subscription_enabled',
        'rent_enabled', 'purchase_enabled', 'rent_price_minor',
        'purchase_price_minor', 'rental_duration_hours',
        'subscription_plan_ids', 'creator_share_bps_override',
        'currency', 'status', 'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'subscription_enabled' => 'boolean',
            'rent_enabled' => 'boolean',
            'purchase_enabled' => 'boolean',
            'subscription_plan_ids' => 'array',
            'approved_at' => 'datetime',
        ];
    }

    public function content()
    {
        return $this->morphTo();
    }
}
