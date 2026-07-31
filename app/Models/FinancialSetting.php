<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialSetting extends Model
{
    protected $fillable = [
        'currency',
        'commission_rate',
        'creator_hold_days',
        'min_withdrawal_amount',
        'auto_payout_enabled',
        'manual_payout_enabled',
        'unverified_creator_earns',
        'earnings_pending_until_approval',
        'unverified_earnings_policy',
        'refund_chargeback_policy',
        'iotec_disbursement_enabled',
        'pawapay_disbursement_enabled',
        'creator_share_bps',
        'platform_share_bps',
        'subscription_pool_bps',
        'settlement_frequency',
        'min_withdrawal_minor',
        'max_withdrawal_minor',
        'withdrawal_fee_minor',
        'daily_withdrawal_limit_minor',
        'monthly_withdrawal_limit_minor',
        'manual_approval_required',
        'allowed_payout_methods',
        'qualified_watch_seconds',
        'max_qualified_plays_per_day',
        'creator_upload_settings',
    ];

    protected function casts(): array
    {
        return [
            'commission_rate' => 'decimal:2',
            'min_withdrawal_amount' => 'decimal:2',
            'auto_payout_enabled' => 'boolean',
            'manual_payout_enabled' => 'boolean',
            'unverified_creator_earns' => 'boolean',
            'earnings_pending_until_approval' => 'boolean',
            'iotec_disbursement_enabled' => 'boolean',
            'pawapay_disbursement_enabled' => 'boolean',
            'manual_approval_required' => 'boolean',
            'allowed_payout_methods' => 'array',
            'creator_upload_settings' => 'array',
        ];
    }

    /**
     * Get the current financial settings (singleton row).
     */
    public static function current(): ?self
    {
        return static::first();
    }

    protected static function booted(): void
    {
        static::saving(function (FinancialSetting $settings): void {
            $creatorShare = (int) ($settings->creator_share_bps ?? 7000);
            $platformShare = (int) ($settings->platform_share_bps ?? 3000);
            if ($creatorShare + $platformShare !== 10000) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'creator_share_bps' => ['Creator and platform shares must add up to exactly 100%.'],
                ]);
            }

            if ($settings->auto_payout_enabled) {
                $hasAutomaticRail = CreatorPayoutOption::query()
                    ->where('automation_type', 'automatic')
                    ->where('is_enabled', true)
                    ->where('is_configured', true)
                    ->exists();
                if (! $hasAutomaticRail) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'auto_payout_enabled' => ['Enable and configure an automatic payout provider before turning on automatic payouts.'],
                    ]);
                }
            }
        });
    }
}
