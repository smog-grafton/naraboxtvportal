<?php

namespace App\Services;

use App\Models\PartnerEarning;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardMetricsService
{
    /** Keep the dashboard widgets on one consistent snapshot per request. */
    private ?array $snapshot = null;

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        if ($this->snapshot !== null) {
            return $this->snapshot;
        }

        $now = now();
        $today = $now->copy()->startOfDay();
        $week = $now->copy()->startOfWeek();
        $month = $now->copy()->startOfMonth();

        $this->snapshot = [
            'subscriptions' => $this->subscriptionMetrics($now),
            'rentals' => $this->accessMetrics('RENT', $today, $week, $month),
            'purchases' => $this->accessMetrics('BUY', $today, $week, $month),
            'revenue' => $this->revenueMetrics($today, $week, $month),
            'creators' => $this->creatorMetrics(),
            'partners' => $this->partnerMetrics(),
            'generated_at' => $now->toIso8601String(),
        ];

        return $this->snapshot;
    }

    /** @return array<string, mixed> */
    private function subscriptionMetrics(\DateTimeInterface $now): array
    {
        $planCounts = collect();
        $configuredPlans = DB::table('subscription_plans')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'name', 'slug', 'duration_days']);

        foreach ($configuredPlans as $plan) {
            $planCounts->put((string) $plan->name, [
                'label' => (string) $plan->name,
                'slug' => (string) $plan->slug,
                'duration_days' => (int) $plan->duration_days,
                'users' => 0,
            ]);
        }

        $usersByPlan = [];

        // Select the longest-lived current entitlement when a renewal overlap
        // exists. This keeps one account in one plan bucket.
        $currentRows = DB::table('user_subscriptions as us')
            ->leftJoin('subscription_plans as sp', 'sp.id', '=', 'us.subscription_plan_id')
            ->where('us.status', 'ACTIVE')
            ->where('us.expires_at', '>', $now)
            ->orderByDesc('us.expires_at')
            ->get([
                'us.user_id',
                'us.subscription_plan_id',
                'sp.name as plan_name',
                'sp.slug as plan_slug',
                'sp.duration_days',
            ]);

        foreach ($currentRows as $row) {
            if (isset($usersByPlan[(int) $row->user_id])) {
                continue;
            }

            $label = filled($row->plan_name)
                ? (string) $row->plan_name
                : 'Plan #'.(string) $row->subscription_plan_id;
            $usersByPlan[(int) $row->user_id] = $label;
            $this->incrementPlanCount($planCounts, $label, $row->plan_slug, $row->duration_days);
        }

        $legacyRows = DB::table('subscriptions')
            ->whereRaw("UPPER(status) = 'ACTIVE'")
            ->where(function ($query) use ($now): void {
                $query->whereNull('end_date')->orWhere('end_date', '>', $now);
            })
            ->orderByDesc('end_date')
            ->get(['user_id', 'plan']);

        foreach ($legacyRows as $row) {
            $userId = (int) $row->user_id;
            if (isset($usersByPlan[$userId])) {
                continue;
            }

            $label = trim((string) $row->plan) ?: 'Legacy plan';
            $usersByPlan[$userId] = $label;
            $this->incrementPlanCount($planCounts, $label, null, null);
        }

        // Manual administrator-granted entitlements are valid only when there
        // is no subscription ledger history for that account, matching the
        // playback entitlement rules.
        $ledgerUserIds = array_fill_keys(array_map(
            'intval',
            DB::table('user_subscriptions')->distinct()->pluck('user_id')->all()
        ), true);
        foreach (DB::table('subscriptions')->distinct()->pluck('user_id')->all() as $userId) {
            $ledgerUserIds[(int) $userId] = true;
        }

        $manualRows = DB::table('users')
            ->where('plan_status', 'ACTIVE')
            ->whereRaw("UPPER(plan) <> 'FREE'")
            ->where(function ($query) use ($now): void {
                $query->whereNull('renewal_date')->orWhere('renewal_date', '>', $now);
            })
            ->get(['id', 'plan']);

        foreach ($manualRows as $row) {
            $userId = (int) $row->id;
            if (isset($usersByPlan[$userId]) || isset($ledgerUserIds[$userId])) {
                continue;
            }

            $label = trim((string) $row->plan) ?: 'Manual plan';
            $usersByPlan[$userId] = $label;
            $this->incrementPlanCount($planCounts, $label, null, null);
        }

        return [
            'active_users' => count($usersByPlan),
            'plans' => $planCounts->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function accessMetrics(string $transactionType, \DateTimeInterface $today, \DateTimeInterface $week, \DateTimeInterface $month): array
    {
        $isRental = $transactionType === 'RENT';
        $modernTable = $isRental ? 'user_rentals' : 'user_purchases';
        $legacyTable = $isRental ? 'rentals' : 'purchases';
        $modernDate = $isRental ? 'rented_at' : 'purchased_at';

        $modernUsers = DB::table($modernTable)->distinct()->pluck('user_id');
        $legacyUsers = DB::table($legacyTable)->distinct()->pluck('user_id');
        $uniqueUsers = $modernUsers->merge($legacyUsers)->unique()->count();

        $modernQuery = DB::table($modernTable);
        if ($isRental) {
            $modernQuery->where('is_active', true)
                ->where(function ($query) {
                    $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                });
        }

        $legacyQuery = DB::table($legacyTable);
        if ($isRental) {
            $legacyQuery->where('is_active', true)
                ->where(function ($query) {
                    $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                });
        }

        $periodUsers = function (\DateTimeInterface $from) use ($transactionType): int {
            return (int) DB::table('payment_transactions')
                ->where('status', 'SUCCESS')
                ->where('type', $transactionType)
                ->where('created_at', '>=', $from)
                ->distinct('user_id')
                ->count('user_id');
        };

        return [
            'unique_users' => $uniqueUsers,
            'active_items' => $isRental ? $modernQuery->count() + $legacyQuery->count() : null,
            'records' => DB::table($modernTable)->count() + DB::table($legacyTable)->count(),
            'today_users' => $periodUsers($today),
            'week_users' => $periodUsers($week),
            'month_users' => $periodUsers($month),
            'date_column' => $modernDate,
        ];
    }

    /** @return array<string, mixed> */
    private function revenueMetrics(\DateTimeInterface $today, \DateTimeInterface $week, \DateTimeInterface $month): array
    {
        $successful = DB::table('payment_transactions')->where('status', 'SUCCESS');
        $byType = (clone $successful)
            ->select('type')
            ->selectRaw('COALESCE(SUM(amount), 0) AS total')
            ->groupBy('type')
            ->pluck('total', 'type')
            ->map(fn ($value): float => (float) $value)
            ->all();

        $gross = function (?\DateTimeInterface $from) use ($successful): float {
            $query = clone $successful;
            if ($from) {
                $query->where('created_at', '>=', $from);
            }

            return (float) $query->sum('amount');
        };

        $creator = DB::table('creator_earnings')
            ->whereNotIn('status', ['reversed'])
            ->sum(DB::raw('COALESCE(creator_amount_minor, creator_amount)'));
        $partners = DB::table('partner_earnings')
            ->whereNotIn('status', ['reversed'])
            ->sum(DB::raw('COALESCE(NULLIF(partner_amount_minor, 0), partner_amount)'));
        $allGross = $gross(null);

        return [
            'today' => $gross($today),
            'week' => $gross($week),
            'month' => $gross($month),
            'all' => $allGross,
            'successful_transactions' => (clone $successful)->count(),
            'by_type' => $byType,
            'creator_allocated' => (float) $creator,
            'partner_allocated' => (float) $partners,
            'retained' => max(0, $allGross - (float) $creator - (float) $partners),
        ];
    }

    /** @return array<string, mixed> */
    private function creatorMetrics(): array
    {
        $ledger = DB::table('creator_ledger_entries')
            ->where('status', 'posted')
            ->whereNotIn('reference_type', [PartnerEarning::class, 'partner_withdrawal', 'partner_adjustment'])
            ->selectRaw('bucket, COALESCE(SUM(amount_minor), 0) AS total')
            ->groupBy('bucket')
            ->pluck('total', 'bucket');

        return [
            'approved_applications' => DB::table('creator_applications')->where('status', 'approved')->count(),
            'pending_applications' => DB::table('creator_applications')->whereIn('status', ['submitted', 'under_review', 'additional_information_required'])->count(),
            'verified_creators' => DB::table('creator_permissions')->where('identity_verified', true)->where('is_suspended', false)->where('is_revoked', false)->count(),
            'pending_vj_claims' => DB::table('vj_claim_requests')->whereIn('status', ['submitted', 'under_review'])->count(),
            'pending_minor' => (int) ($ledger['pending'] ?? 0),
            'available_minor' => (int) ($ledger['available'] ?? 0),
            'paid_minor' => (int) ($ledger['paid'] ?? 0),
        ];
    }

    /** @return array<string, mixed> */
    private function partnerMetrics(): array
    {
        $ledger = DB::table('creator_ledger_entries')
            ->where('status', 'posted')
            ->whereIn('reference_type', [PartnerEarning::class, 'partner_withdrawal', 'partner_adjustment'])
            ->selectRaw('bucket, COALESCE(SUM(amount_minor), 0) AS total')
            ->groupBy('bucket')
            ->pluck('total', 'bucket');

        return [
            'active_partners' => DB::table('partners')->where('status', 'active')->count(),
            'referred_users' => DB::table('partner_attributions')->distinct()->count('user_id'),
            'pending_minor' => (int) ($ledger['pending'] ?? 0),
            'available_minor' => (int) ($ledger['available'] ?? 0),
            'paid_minor' => (int) ($ledger['paid'] ?? 0),
        ];
    }

    private function incrementPlanCount(Collection $plans, string $label, ?string $slug, mixed $durationDays): void
    {
        $existing = $plans->get($label, [
            'label' => $label,
            'slug' => $slug,
            'duration_days' => $durationDays !== null ? (int) $durationDays : null,
            'users' => 0,
        ]);
        $existing['users']++;
        $plans->put($label, $existing);
    }
}
