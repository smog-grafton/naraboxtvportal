<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialSetting extends Model
{
    protected $fillable = [
        'commission_rate',
        'creator_hold_days',
        'min_withdrawal_amount',
        'auto_payout_enabled',
        'unverified_creator_earns',
        'iotec_disbursement_enabled',
        'pawapay_disbursement_enabled',
    ];

    protected function casts(): array
    {
        return [
            'commission_rate' => 'decimal:2',
            'min_withdrawal_amount' => 'decimal:2',
            'auto_payout_enabled' => 'boolean',
            'unverified_creator_earns' => 'boolean',
            'iotec_disbursement_enabled' => 'boolean',
            'pawapay_disbursement_enabled' => 'boolean',
        ];
    }

    /**
     * Get the current financial settings (singleton row).
     */
    public static function current(): ?self
    {
        return static::first();
    }
}
