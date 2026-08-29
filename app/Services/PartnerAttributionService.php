<?php

namespace App\Services;

use App\Models\Partner;
use App\Models\PartnerAttribution;
use App\Models\PartnerCampaign;
use App\Models\PartnerReferralEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PartnerAttributionService
{
    public function resolve(?string $code = null, ?string $campaignCode = null): array
    {
        $code = trim((string) $code);
        $campaignCode = trim((string) $campaignCode);
        if ($code === '' && $campaignCode === '') {
            return [null, null];
        }

        $campaign = null;
        $partner = null;

        if ($campaignCode !== '') {
            $campaign = PartnerCampaign::query()
                ->where(function ($query) use ($campaignCode): void {
                    $query->where('referral_code', $campaignCode)
                        ->orWhere('slug', Str::slug($campaignCode));
                })
                ->first();
            $partner = $campaign?->partner;
        }

        if (! $partner && $code !== '') {
            $partner = Partner::query()
                ->where(function ($query) use ($code): void {
                    $query->whereRaw('LOWER(referral_code) = ?', [strtolower($code)])
                        ->orWhere('slug', Str::slug($code));
                })
                ->first();
        }

        if (! $partner) {
            return [null, null];
        }

        if ($campaign && $campaign->partner_id !== $partner->id) {
            return [null, null];
        }

        return [$partner, $campaign];
    }

    public function publicProfile(string $code, ?string $campaignCode = null): ?array
    {
        [$partner, $campaign] = $this->resolve($code, $campaignCode);
        if (! $partner || ! $partner->isActiveAt() || ($campaign && ! $campaign->isActiveAt())) {
            return null;
        }

        return [
            'display_name' => $partner->display_name,
            'slug' => $partner->slug,
            'referral_code' => $partner->referral_code,
            'campaign' => $campaign ? [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'slug' => $campaign->slug,
                'referral_code' => $campaign->referral_code,
            ] : null,
        ];
    }

    public function recordVisit(Request $request, string $code, ?string $campaignCode = null): ?PartnerReferralEvent
    {
        [$partner, $campaign] = $this->resolve($code, $campaignCode);
        if (! $partner || ! $partner->isActiveAt() || ($campaign && ! $campaign->isActiveAt())) {
            return null;
        }

        $visitorKey = hash('sha256', implode('|', [
            (string) $request->ip(),
            (string) $request->userAgent(),
            now()->toDateString(),
        ]));

        $existing = PartnerReferralEvent::query()
            ->where('partner_id', $partner->id)
            ->where('campaign_id', $campaign?->id)
            ->where('visitor_key', $visitorKey)
            ->where('event_type', 'visit')
            ->where('occurred_at', '>=', now()->startOfDay())
            ->first();
        if ($existing) {
            return $existing;
        }

        return PartnerReferralEvent::create([
            'partner_id' => $partner->id,
            'campaign_id' => $campaign?->id,
            'user_id' => $request->user()?->id,
            'event_type' => 'visit',
            'visitor_key' => $visitorKey,
            'referral_code' => $campaign?->referral_code ?? $partner->referral_code,
            'metadata' => [
                'landing_path' => Str::limit((string) $request->input('landing_path', ''), 500, ''),
                'user_agent_family' => Str::limit((string) $request->header('User-Agent', ''), 120, ''),
            ],
            'occurred_at' => now(),
        ]);
    }

    public function attributeFromRequest(User $user, Request $request, string $method = 'registration_selection'): ?PartnerAttribution
    {
        $code = $request->input('partner_referral_code')
            ?? $request->input('referral_code')
            ?? $request->input('partner_slug');
        $campaignCode = $request->input('partner_campaign') ?? $request->input('campaign');

        return $this->attribute(
            $user,
            is_string($code) ? $code : null,
            is_string($campaignCode) ? $campaignCode : null,
            $method,
            [
                'discovery_source' => $request->input('discovery_source'),
                'source' => $request->input('source'),
            ]
        );
    }

    public function attribute(
        User $user,
        ?string $code,
        ?string $campaignCode,
        string $method = 'registration_selection',
        array $metadata = []
    ): ?PartnerAttribution {
        [$partner, $campaign] = $this->resolve($code, $campaignCode);
        if (! $partner || ! $partner->isActiveAt() || ($campaign && ! $campaign->isActiveAt())) {
            return null;
        }
        if ($partner->user_id === $user->id) {
            return null;
        }

        return DB::transaction(function () use ($user, $partner, $campaign, $code, $method, $metadata): ?PartnerAttribution {
            $existing = PartnerAttribution::query()->where('user_id', $user->id)->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }

            $attributedAt = now();
            $validUntil = $attributedAt->copy()->addDays(max(1, (int) $partner->commission_duration_days));
            if ($partner->agreement_end_at && $partner->agreement_end_at->lt($validUntil)) {
                $validUntil = $partner->agreement_end_at->copy();
            }

            $attribution = PartnerAttribution::create([
                'user_id' => $user->id,
                'partner_id' => $partner->id,
                'campaign_id' => $campaign?->id,
                'attribution_method' => Str::limit($method, 32, ''),
                'referral_code' => $campaign?->referral_code ?? $partner->referral_code ?? $code,
                'attributed_at' => $attributedAt,
                'commission_valid_from' => $attributedAt,
                'commission_valid_until' => $validUntil,
                'locked_at' => $attributedAt,
                'metadata' => array_filter($metadata, static fn ($value) => $value !== null && $value !== ''),
            ]);

            PartnerReferralEvent::create([
                'partner_id' => $partner->id,
                'campaign_id' => $campaign?->id,
                'user_id' => $user->id,
                'event_type' => 'registration',
                'referral_code' => $attribution->referral_code,
                'metadata' => ['attribution_id' => $attribution->id],
                'occurred_at' => $attributedAt,
            ]);

            return $attribution;
        });
    }

    public function forTransaction(User $user, \DateTimeInterface $occurredAt): ?PartnerAttribution
    {
        return PartnerAttribution::query()
            ->with(['partner', 'campaign'])
            ->where('user_id', $user->id)
            ->where('commission_valid_from', '<=', $occurredAt)
            ->where(function ($query) use ($occurredAt): void {
                $query->whereNull('commission_valid_until')
                    ->orWhere('commission_valid_until', '>=', $occurredAt);
            })
            ->first();
    }
}
