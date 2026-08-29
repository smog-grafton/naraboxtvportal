<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\PartnerAttribution;
use App\Models\PartnerCampaign;
use App\Models\PartnerEarning;
use App\Models\PartnerBenefitType;
use App\Models\PartnerBenefit;
use App\Models\PartnerReferralEvent;
use App\Models\User;
use App\Services\PartnerAttributionService;
use App\Services\PartnerApplicationService;
use App\Services\PartnerDiscoveryService;
use App\Services\PartnerWalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PartnerController extends Controller
{
    public function __construct(
        private readonly PartnerAttributionService $attributions,
        private readonly PartnerApplicationService $applications,
        private readonly PartnerDiscoveryService $discovery,
        private readonly PartnerWalletService $wallets,
    ) {
    }

    public function discovery(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:20'],
        ]);
        $queryText = trim((string) ($validated['q'] ?? ''));
        $limit = (int) ($validated['limit'] ?? 3);
        $partners = $this->discovery->discover($queryText, $limit)
            ->map(fn (Partner $partner): array => $this->formatDiscoveryPartner($partner));

        return response()->json(['data' => $partners]);
    }

    public function referralCodeAssistance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:64'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:5'],
        ]);
        $result = $this->discovery->assistReferralCode(
            $validated['code'],
            (int) ($validated['limit'] ?? 3)
        );

        return response()->json(['data' => [
            'input' => $result['input'],
            'exact_match' => $result['exact_match']
                ? $this->formatDiscoveryPartner($result['exact_match'])
                : null,
            'suggestions' => $result['suggestions']
                ->map(fn (Partner $partner): array => $this->formatDiscoveryPartner($partner))
                ->values(),
        ]]);
    }

    public function resolve(string $code, Request $request): JsonResponse
    {
        $campaign = $request->query('campaign');
        [$partner, $campaignRecord] = $this->attributions->resolve($code, is_string($campaign) ? $campaign : null);
        if (! $partner || ! $partner->isActiveAt() || ($campaignRecord && ! $campaignRecord->isActiveAt())) {
            return response()->json(['success' => false, 'message' => 'Referral link not found.'], 404);
        }

        return response()->json(['success' => true, 'data' => [
            'partner' => [
                'display_name' => $partner->display_name,
                'slug' => $partner->slug,
                'referral_code' => $partner->referral_code,
                'profile_image' => $partner->profile_image,
                'profile_image_url' => $partner->profileImageUrl(),
            ],
            'campaign' => $campaignRecord ? [
                'name' => $campaignRecord->name,
                'slug' => $campaignRecord->slug,
                'referral_code' => $campaignRecord->referral_code,
            ] : null,
        ]]);
    }

    public function visit(string $code, Request $request): JsonResponse
    {
        $campaign = $request->input('campaign') ?? $request->query('campaign');
        $event = $this->attributions->recordVisit($request, $code, is_string($campaign) ? $campaign : null);

        return $event
            ? response()->json(['success' => true])
            : response()->json(['success' => false, 'message' => 'Referral link not found.'], 404);
    }

    public function attribute(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'partner_referral_code' => ['nullable', 'string', 'max:64'],
            'partner_campaign' => ['nullable', 'string', 'max:128'],
            'attribution_method' => ['nullable', 'string', 'max:32'],
            'discovery_source' => ['nullable', 'string', 'max:64'],
        ]);
        $attribution = $this->attributions->attribute(
            $request->user(),
            $validated['partner_referral_code'] ?? null,
            $validated['partner_campaign'] ?? null,
            $validated['attribution_method'] ?? 'referral_link',
            ['discovery_source' => $validated['discovery_source'] ?? null]
        );

        if (! $attribution) {
            return response()->json(['success' => false, 'message' => 'Referral could not be confirmed.'], 422);
        }

        return response()->json(['success' => true, 'data' => [
            'partner' => $attribution->partner->display_name,
            'attributed_at' => $attribution->attributed_at?->toIso8601String(),
        ]]);
    }

    public function application(Request $request): JsonResponse
    {
        $partner = $request->user()?->partnerProfile;

        return response()->json(['success' => true, 'data' => [
            'application' => $partner ? $this->formatPartner($partner) : null,
        ]]);
    }

    public function apply(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'display_name' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:5000'],
        ]);
        $partner = $this->applications->apply($request->user(), $validated);

        return response()->json(['success' => true, 'data' => [
            'application' => $this->formatPartner($partner),
        ]], $partner->wasRecentlyCreated ? 201 : 200);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $partner = $this->currentPartner($request);
        if (! $partner) {
            return $this->notPartner();
        }

        $today = now()->startOfDay();
        $week = now()->startOfWeek();
        $month = now()->startOfMonth();
        $earnings = PartnerEarning::query()->where('partner_id', $partner->id);
        $attributions = PartnerAttribution::query()->where('partner_id', $partner->id);
        $events = PartnerReferralEvent::query()->where('partner_id', $partner->id);
        $balance = $this->wallets->balances($partner->user);
        $payingUsers = (clone $earnings)->distinct('user_id')->count('user_id');
        $referredUsers = (clone $attributions)->count();
        $commissionEarned = $this->sumCanonicalAmount(clone $earnings, 'partner_amount_minor', 'partner_amount');
        $revenueGenerated = $this->sumCanonicalAmount(clone $earnings, 'commissionable_amount_minor', 'commissionable_amount');
        $lifetimeEarnings = $this->sumCanonicalAmount(
            (clone $earnings)->whereNotIn('status', ['reversed']),
            'partner_amount_minor',
            'partner_amount'
        );
        $activeBenefits = $partner->benefits()
            ->currentlyActive()
            ->whereHas('benefitType', fn ($query) => $query->where('globally_enabled', true))
            ->with('benefitType')
            ->withCount([
                'claims as usage_count' => fn ($query) => $query->where('status', 'redeemed'),
                'claims as conversion_count' => fn ($query) => $query
                    ->where('status', 'redeemed')
                    ->whereNotNull('transaction_id'),
            ])
            ->get()
            ->map(fn ($benefit): array => [
                'id' => $benefit->id,
                'code' => $benefit->benefitType->code,
                'name' => $benefit->benefitType->name,
                'description' => $benefit->benefitType->description,
                'configuration' => $benefit->resolvedConfiguration(),
                'usage_count' => (int) $benefit->usage_count,
                'conversion_count' => (int) $benefit->conversion_count,
                'starts_at' => $benefit->starts_at?->toIso8601String(),
                'ends_at' => $benefit->ends_at?->toIso8601String(),
            ]);

        return response()->json(['success' => true, 'data' => [
            'partner' => $this->formatPartner($partner),
            'metrics' => [
                'referred_users' => $referredUsers,
                'new_users_today' => (clone $attributions)->where('attributed_at', '>=', $today)->count(),
                'new_users_this_week' => (clone $attributions)->where('attributed_at', '>=', $week)->count(),
                'new_users_this_month' => (clone $attributions)->where('attributed_at', '>=', $month)->count(),
                'paying_users' => $payingUsers,
                'non_paying_users' => max(0, $referredUsers - $payingUsers),
                'conversion_rate' => $referredUsers > 0 ? round(($payingUsers / $referredUsers) * 100, 2) : 0,
                'referral_visits' => (clone $events)->where('event_type', 'visit')->count(),
                'registrations' => (clone $events)->where('event_type', 'registration')->count(),
                'transactions' => (clone $earnings)->count(),
                'revenue_generated_minor' => $revenueGenerated,
                'commission_earned_minor' => $commissionEarned,
                'lifetime_earnings_minor' => $lifetimeEarnings,
                'total_paid_minor' => (int) ($balance['paid_minor'] ?? 0),
                'average_revenue_per_referred_user_minor' => $referredUsers > 0 ? intdiv($revenueGenerated, $referredUsers) : 0,
                'average_commission_per_paying_user_minor' => $payingUsers > 0 ? intdiv($commissionEarned, $payingUsers) : 0,
            ],
            'wallet' => $balance,
            'recent_earnings' => (clone $earnings)->with(['transaction.transactionable', 'campaign'])
                ->latest('earned_at')->limit(10)->get()->map(fn (PartnerEarning $earning) => $this->formatEarning($earning)),
            'referral_link' => $this->referralLink($partner),
            'active_benefits' => $activeBenefits,
        ]]);
    }

    public function analytics(Request $request): JsonResponse
    {
        $partner = $this->currentPartner($request);
        if (! $partner) return $this->notPartner();

        $days = max(7, min(365, (int) $request->query('days', 30)));
        $from = now()->subDays($days - 1)->startOfDay();
        $events = PartnerReferralEvent::where('partner_id', $partner->id)->where('occurred_at', '>=', $from)->get();
        $earnings = PartnerEarning::where('partner_id', $partner->id)->where('earned_at', '>=', $from)->get();
        $rows = collect(range(0, $days - 1))->map(function (int $offset) use ($from, $events, $earnings): array {
            $date = $from->copy()->addDays($offset)->toDateString();
            $dayEvents = $events->filter(fn (PartnerReferralEvent $event) => $event->occurred_at?->toDateString() === $date);
            $dayEarnings = $earnings->filter(fn (PartnerEarning $earning) => $earning->earned_at?->toDateString() === $date);

            return [
                'date' => $date,
                'visits' => $dayEvents->where('event_type', 'visit')->count(),
                'registrations' => $dayEvents->where('event_type', 'registration')->count(),
                'transactions' => $dayEarnings->count(),
                'revenue_generated_minor' => $this->sumCanonicalAmount($dayEarnings, 'commissionable_amount_minor', 'commissionable_amount'),
                'commission_minor' => $this->sumCanonicalAmount($dayEarnings, 'partner_amount_minor', 'partner_amount'),
            ];
        });

        return response()->json(['success' => true, 'data' => ['days' => $rows]]);
    }

    public function referrals(Request $request): JsonResponse
    {
        $partner = $this->currentPartner($request);
        if (! $partner) return $this->notPartner();

        $paginator = PartnerAttribution::query()->where('partner_id', $partner->id)
            ->with(['campaign', 'user:id,created_at'])
            ->latest('attributed_at')->paginate(min(50, max(1, (int) $request->query('per_page', 20))));
        $payingIds = PartnerEarning::where('partner_id', $partner->id)->pluck('user_id')->unique()->flip();
        $items = $paginator->getCollection()->map(fn (PartnerAttribution $attribution) => [
            'viewer_ref' => 'viewer-'.substr(hash('sha256', $partner->id.':'.$attribution->user_id), 0, 10),
            'registered_at' => $attribution->attributed_at?->toIso8601String(),
            'commission_valid_until' => $attribution->commission_valid_until?->toIso8601String(),
            'paying' => $payingIds->has($attribution->user_id),
            'campaign' => $attribution->campaign?->name,
        ]);

        return response()->json(['success' => true, 'data' => [
            'referrals' => $items,
            'pagination' => $this->pagination($paginator),
        ]]);
    }

    public function transactions(Request $request): JsonResponse
    {
        $partner = $this->currentPartner($request);
        if (! $partner) return $this->notPartner();

        $paginator = PartnerEarning::query()->where('partner_id', $partner->id)
            ->with(['transaction.transactionable', 'campaign'])->latest('earned_at')
            ->paginate(min(50, max(1, (int) $request->query('per_page', 20))));

        return response()->json(['success' => true, 'data' => [
            'transactions' => $paginator->getCollection()->map(fn (PartnerEarning $earning) => $this->formatEarning($earning)),
            'pagination' => $this->pagination($paginator),
        ]]);
    }

    public function earnings(Request $request): JsonResponse
    {
        return $this->transactions($request);
    }

    public function profile(Request $request): JsonResponse
    {
        $partner = $this->currentPartner($request);
        if (! $partner) return $this->notPartner();

        return response()->json(['success' => true, 'data' => ['partner' => $this->formatPartner($partner)]]);
    }

    public function benefits(Request $request): JsonResponse
    {
        $partner = $this->currentPartner($request);
        if (! $partner) return $this->notPartner();

        $benefits = PartnerBenefitType::query()
            ->orderBy('name')
            ->get()
            ->map(function (PartnerBenefitType $type) use ($partner): array {
                $benefit = $partner->benefits()
                    ->withCount([
                        'claims as usage_count' => fn ($query) => $query->where('status', 'redeemed'),
                        'claims as conversion_count' => fn ($query) => $query
                            ->where('status', 'redeemed')
                            ->whereNotNull('transaction_id'),
                    ])
                    ->where('benefit_type_id', $type->id)
                    ->first();

                return $this->formatBenefitOption($type, $benefit);
            });

        return response()->json(['success' => true, 'data' => ['benefits' => $benefits]]);
    }

    public function updateBenefit(string $code, Request $request): JsonResponse
    {
        $partner = $this->currentPartner($request);
        if (! $partner) return $this->notPartner();

        $type = PartnerBenefitType::query()->where('code', strtoupper($code))->first();
        if (! $type) {
            return response()->json(['success' => false, 'message' => 'Audience benefit not found.'], 404);
        }

        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'percentage_bps' => ['sometimes', 'integer', 'min:100', 'max:5000'],
        ]);

        if ($validated['enabled'] && ! $type->globally_enabled) {
            return response()->json([
                'success' => false,
                'message' => 'This audience benefit is not currently available from NaraBox.',
            ], 422);
        }

        $benefit = $partner->benefits()->firstOrNew(['benefit_type_id' => $type->id]);
        $configuration = $benefit->configuration ?? [];

        if ($type->code === PartnerBenefitType::FIRST_PAYMENT_DISCOUNT) {
            $percentageBps = (int) ($validated['percentage_bps']
                ?? $configuration['percentage_bps']
                ?? $type->default_configuration['percentage_bps']
                ?? 1000);
            $configuration['percentage_bps'] = max(100, min(5000, $percentageBps));
        }

        $benefit->fill([
            'enabled' => (bool) $validated['enabled'],
            'configuration' => $configuration,
        ])->save();

        $benefit->loadCount([
            'claims as usage_count' => fn ($query) => $query->where('status', 'redeemed'),
            'claims as conversion_count' => fn ($query) => $query
                ->where('status', 'redeemed')
                ->whereNotNull('transaction_id'),
        ]);

        return response()->json(['success' => true, 'data' => [
            'benefit' => $this->formatBenefitOption($type, $benefit),
        ]]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $partner = $this->currentPartner($request);
        if (! $partner) return $this->notPartner();

        $validated = $request->validate([
            'display_name' => ['sometimes', 'string', 'max:255'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'profile_photo' => ['sometimes', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ]);
        $updates = collect($validated)->only(['display_name', 'bio'])->all();

        if ($request->hasFile('profile_photo')) {
            $path = $request->file('profile_photo')->store('partners/profile-images', 'public');
            if (! $path) {
                return response()->json(['success' => false, 'message' => 'The profile photo could not be stored.'], 500);
            }

            $previous = trim((string) $partner->profile_image);
            $updates['profile_image'] = $path;
            $partner->update($updates);
            if ($previous !== '' && ! filter_var($previous, FILTER_VALIDATE_URL) && ! str_starts_with($previous, '/storage/')) {
                Storage::disk('public')->delete($previous);
            }
        } elseif ($updates !== []) {
            $partner->update($updates);
        }

        return response()->json(['success' => true, 'data' => [
            'partner' => $this->formatPartner($partner->fresh()),
        ]]);
    }

    public function campaigns(Request $request): JsonResponse
    {
        $partner = $this->currentPartner($request);
        if (! $partner) return $this->notPartner();

        $campaigns = $partner->campaigns()->latest()->get()->map(fn (PartnerCampaign $campaign) => [
            'id' => $campaign->id,
            'name' => $campaign->name,
            'slug' => $campaign->slug,
            'referral_code' => $campaign->referral_code,
            'status' => $campaign->status,
            'commission_bps' => $campaign->commission_bps,
            'link' => $this->referralLink($partner, $campaign),
            'visits' => PartnerReferralEvent::where('campaign_id', $campaign->id)->where('event_type', 'visit')->count(),
            'registrations' => $campaign->attributions()->count(),
            'transactions' => $campaign->partner->earnings()->where('campaign_id', $campaign->id)->count(),
        ]);

        if ($request->isMethod('post')) {
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:120'],
                'slug' => ['nullable', 'string', 'max:80'],
                'commission_bps' => ['nullable', 'integer', 'min:0', 'max:10000'],
            ]);
            $slug = Str::slug($validated['slug'] ?? $validated['name']);
            $code = strtoupper(Str::slug($partner->slug.'-'.$slug, '-'));
            $suffix = 1;
            while (PartnerCampaign::where('referral_code', $code)->exists()) {
                $code = strtoupper(Str::slug($partner->slug.'-'.$slug.'-'.$suffix++, '-'));
            }
            $campaign = $partner->campaigns()->create([
                'name' => $validated['name'],
                'slug' => $slug,
                'referral_code' => $code,
                'commission_bps' => $validated['commission_bps'] ?? null,
                'status' => 'active',
            ]);

            return response()->json(['success' => true, 'data' => ['campaign' => [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'slug' => $campaign->slug,
                'referral_code' => $campaign->referral_code,
                'link' => $this->referralLink($partner, $campaign),
            ]]], 201);
        }

        return response()->json(['success' => true, 'data' => ['campaigns' => $campaigns]]);
    }

    private function currentPartner(Request $request): ?Partner
    {
        $user = $request->user();
        if (! $user) return null;

        $partner = $user->partnerProfile;

        return $partner && $partner->isActiveAt() ? $partner->load('user') : null;
    }

    private function notPartner(): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Partner access is not active.'], 403);
    }

    private function formatPartner(Partner $partner): array
    {
        return [
            'id' => $partner->id,
            'display_name' => $partner->display_name,
            'slug' => $partner->slug,
            'referral_code' => $partner->referral_code,
            'profile_image' => $partner->profile_image,
            'profile_image_url' => $partner->profileImageUrl(),
            'bio' => $partner->bio,
            'status' => $partner->status,
            'commission_duration_days' => $partner->commission_duration_days,
            'payout_hold_days' => $partner->payout_hold_days,
            'minimum_payout_minor' => $partner->minimum_payout_minor,
        ];
    }

    private function formatBenefitOption(PartnerBenefitType $type, ?PartnerBenefit $benefit): array
    {
        return [
            'id' => $benefit?->id,
            'code' => $type->code,
            'name' => $type->name,
            'description' => $type->description,
            'available' => (bool) $type->globally_enabled,
            'enabled' => (bool) ($benefit?->enabled && $type->globally_enabled),
            'configuration' => array_replace(
                $type->default_configuration ?? [],
                $benefit?->configuration ?? [],
            ),
            'usage_count' => (int) ($benefit?->usage_count ?? 0),
            'conversion_count' => (int) ($benefit?->conversion_count ?? 0),
        ];
    }

    private function referralLink(Partner $partner, ?PartnerCampaign $campaign = null): string
    {
        $path = '/join/'.rawurlencode($partner->slug);
        if ($campaign) {
            $path .= '?campaign='.rawurlencode($campaign->referral_code);
        }

        return rtrim((string) config('app.frontend_url', env('FRONTEND_URL', 'https://naraboxtv.com')), '/').$path;
    }

    private function formatDiscoveryPartner(Partner $partner): array
    {
        return [
            'id' => $partner->id,
            'display_name' => $partner->display_name,
            'slug' => $partner->slug,
            'referral_code' => $partner->referral_code,
            'profile_image' => $partner->profile_image,
            'profile_image_url' => $partner->profileImageUrl(),
        ];
    }

    private function formatEarning(PartnerEarning $earning): array
    {
        $transaction = $earning->transaction;
        $content = $transaction?->transactionable;

        return [
            'id' => $earning->id,
            'transaction_ref' => $transaction?->transaction_ref,
            'transaction_type' => $transaction?->type,
            'title' => $content?->title ?? $content?->name,
            'gross_amount_minor' => $this->canonicalAmount($earning->gross_amount_minor, $earning->gross_amount),
            'commissionable_amount_minor' => $this->canonicalAmount($earning->commissionable_amount_minor, $earning->commissionable_amount),
            'creator_amount_minor' => $this->canonicalAmount($earning->creator_amount_minor, $earning->creator_amount),
            'platform_share_before_partner_minor' => $this->canonicalAmount($earning->platform_share_before_partner_minor, $earning->platform_share_before_partner),
            'partner_rate_bps' => (int) $earning->partner_rate_bps,
            'partner_amount_minor' => $this->canonicalAmount($earning->partner_amount_minor, $earning->partner_amount),
            'platform_final_amount_minor' => $this->canonicalAmount($earning->platform_final_amount_minor, $earning->platform_final_amount),
            'status' => $earning->status,
            'earned_at' => $earning->earned_at?->toIso8601String(),
            'available_at' => $earning->available_at?->toIso8601String(),
            'campaign' => $earning->campaign?->name,
        ];
    }

    private function pagination($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ];
    }

    private function canonicalAmount(mixed $minor, mixed $major): int
    {
        $minor = (int) $minor;
        $major = (float) $major;

        // Early partner rows can predate the minor-unit columns and therefore
        // contain a zero default there while the canonical UGX amount is in
        // the decimal column. New rows populate both columns.
        return $minor !== 0 || $major == 0.0 ? $minor : (int) round($major);
    }

    private function sumCanonicalAmount($query, string $minorColumn, string $majorColumn): int
    {
        $total = 0;
        $rows = $query instanceof \Illuminate\Support\Collection
            ? $query
            : $query->get([$minorColumn, $majorColumn]);

        foreach ($rows as $row) {
            $total += $this->canonicalAmount($row->{$minorColumn}, $row->{$majorColumn});
        }

        return $total;
    }
}
