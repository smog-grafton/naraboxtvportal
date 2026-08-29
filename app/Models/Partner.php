<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Partner extends Model
{
    protected $fillable = [
        'user_id',
        'display_name',
        'slug',
        'referral_code',
        'profile_image',
        'bio',
        'social_links',
        'status',
        'default_commission_bps',
        'attribution_window_days',
        'commission_duration_days',
        'payout_hold_days',
        'minimum_payout_minor',
        'rate_overrides',
        'agreement_start_at',
        'agreement_end_at',
        'notes',
        'approved_at',
        'approved_by',
        'suspended_at',
        'suspended_by',
    ];

    protected function casts(): array
    {
        return [
            'social_links' => 'array',
            'rate_overrides' => 'array',
            'agreement_start_at' => 'datetime',
            'agreement_end_at' => 'datetime',
            'approved_at' => 'datetime',
            'suspended_at' => 'datetime',
            'default_commission_bps' => 'integer',
            'attribution_window_days' => 'integer',
            'commission_duration_days' => 'integer',
            'payout_hold_days' => 'integer',
            'minimum_payout_minor' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(PartnerCampaign::class);
    }

    public function attributions(): HasMany
    {
        return $this->hasMany(PartnerAttribution::class);
    }

    public function referralEvents(): HasMany
    {
        return $this->hasMany(PartnerReferralEvent::class);
    }

    public function earnings(): HasMany
    {
        return $this->hasMany(PartnerEarning::class);
    }

    public function benefits(): HasMany
    {
        return $this->hasMany(PartnerBenefit::class);
    }

    public function benefitClaims(): HasMany
    {
        return $this->hasMany(PartnerBenefitClaim::class);
    }

    public function payoutMethods(): HasMany
    {
        return $this->hasMany(CreatorPayoutMethod::class, 'user_id', 'user_id');
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(CreatorWithdrawalRequest::class, 'user_id', 'user_id')
            ->where('beneficiary_type', 'partner');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function isActiveAt(?\DateTimeInterface $at = null): bool
    {
        $timezone = (string) config('app.partner_timezone', 'Africa/Kampala');
        $at = $at
            ? CarbonImmutable::instance($at)->setTimezone($timezone)
            : CarbonImmutable::now($timezone);

        // The admin selects agreement dates, not moments in time. Comparing
        // calendar dates makes the start date inclusive from 00:00 in Uganda
        // and keeps the end date active through the end of that day.
        $startDate = $this->agreement_start_at
            ? CarbonImmutable::instance($this->agreement_start_at)->setTimezone($timezone)->toDateString()
            : null;
        $endDate = $this->agreement_end_at
            ? CarbonImmutable::instance($this->agreement_end_at)->setTimezone($timezone)->toDateString()
            : null;

        return $this->status === 'active'
            && (! $startDate || $at->toDateString() >= $startDate)
            && (! $endDate || $at->toDateString() <= $endDate);
    }

    public function rateFor(string $transactionType, ?PartnerCampaign $campaign = null): int
    {
        if ($campaign?->commission_bps !== null) {
            return max(0, min(10000, (int) $campaign->commission_bps));
        }

        $override = $this->rate_overrides[strtoupper($transactionType)]
            ?? $this->rate_overrides[strtolower($transactionType)]
            ?? null;

        return max(0, min(10000, (int) ($override ?? $this->default_commission_bps)));
    }

    public function profileImageUrl(): ?string
    {
        $path = trim((string) $this->profile_image);
        if ($path === '') {
            return null;
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        if (str_starts_with($path, '/storage/')) {
            return rtrim((string) config('app.url'), '/').$path;
        }

        return Storage::disk('public')->url(ltrim($path, '/'));
    }
}
