<?php

namespace App\Services;

use App\Models\CreatorMonetizationSetting;
use App\Models\CreatorPermission;
use App\Models\CreatorSettlement;
use App\Models\CreatorSettlementAllocation;
use App\Models\FinancialSetting;
use App\Models\MediaLibrary;
use App\Models\Movie;
use App\Models\PaymentTransaction;
use App\Models\PlaybackSession;
use App\Models\TVShow;
use App\Models\User;
use App\Models\UserSubscription;
use App\Models\VJ;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreatorSettlementService
{
    public function __construct(
        private readonly CreatorWalletService $wallets,
        private readonly CreatorAuditService $audit,
        private readonly CreatorCommunicationService $communications
    ) {}

    public function calculate(string $period): CreatorSettlement
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $period)) {
            throw ValidationException::withMessages(['period' => ['Use YYYY-MM format.']]);
        }

        $start = CarbonImmutable::createFromFormat('Y-m', $period)->startOfMonth();
        $end = $start->endOfMonth();
        $settings = FinancialSetting::current();
        if (! $settings) {
            throw ValidationException::withMessages(['period' => ['Financial settings are not configured.']]);
        }

        return DB::transaction(function () use ($period, $start, $end, $settings) {
            $settlement = CreatorSettlement::query()->lockForUpdate()->firstOrNew(['period' => $period]);
            if ($settlement->exists && in_array($settlement->status, ['approved', 'finalized'], true)) {
                throw ValidationException::withMessages(['period' => ['Approved or finalized settlements cannot be recalculated.']]);
            }
            $settlement->fill([
                'settlement_type' => 'subscription_pool',
                'status' => 'calculating',
                'failure_reason' => null,
                'cancelled_at' => null,
                'cancelled_by' => null,
            ]);
            $settlement->save();
            $settlement->allocations()->delete();

            $eligibleRevenue = PaymentTransaction::query()
                ->where('type', 'SUBSCRIPTION')
                ->where('status', 'SUCCESS')
                ->whereBetween('created_at', [$start, $end])
                ->get()
                ->sum(fn (PaymentTransaction $transaction) => MoneyService::toMinor((string) $transaction->amount));
            $poolBps = (int) ($settings->subscription_pool_bps ?? 4000);
            $poolMinor = MoneyService::share((int) $eligibleRevenue, $poolBps);
            $threshold = (int) ($settings->qualified_watch_seconds ?? 300);
            $dailyCap = max(1, (int) ($settings->max_qualified_plays_per_day ?? 3));
            $metrics = [];

            $sessions = PlaybackSession::query()
                ->whereNotNull('user_id')
                ->whereBetween('started_at', [$start, $end])
                ->where('total_watch_seconds', '>=', $threshold)
                ->orderBy('started_at')
                ->get()
                ->groupBy(fn (PlaybackSession $session) => implode(':', [
                    $session->user_id,
                    $session->media_type,
                    $session->media_id,
                    $session->started_at?->toDateString(),
                ]))
                ->flatMap(fn ($group) => $group->take($dailyCap));

            foreach ($sessions as $session) {
                if (! $this->viewerHadSubscription($session, $start, $end)) {
                    continue;
                }
                $content = strtoupper($session->media_type) === 'TV_SHOW'
                    ? TVShow::find($session->media_id)
                    : Movie::find($session->media_id);
                if (! $content || ! $this->subscriptionEligible($content)) {
                    continue;
                }
                $creator = $this->eligibleCreator($content);
                if (! $creator || $creator->id === $session->user_id) {
                    continue;
                }
                $key = $creator->id.':'.$content::class.':'.$content->id;
                $metrics[$key] ??= [
                    'user' => $creator,
                    'content' => $content,
                    'seconds' => 0,
                ];
                $metrics[$key]['seconds'] += (int) $session->total_watch_seconds;
            }

            $totalMetric = array_sum(array_column($metrics, 'seconds'));
            $allocated = 0;
            $lastKey = array_key_last($metrics);
            foreach ($metrics as $key => $metric) {
                $amount = $totalMetric > 0
                    ? intdiv($poolMinor * $metric['seconds'], $totalMetric)
                    : 0;
                if ($key === $lastKey) {
                    $amount = max(0, $poolMinor - $allocated);
                }
                $allocated += $amount;
                $user = $metric['user'];
                $content = $metric['content'];
                CreatorSettlementAllocation::create([
                    'settlement_id' => $settlement->id,
                    'user_id' => $user->id,
                    'wallet_id' => $this->wallets->wallet($user)->id,
                    'content_type' => $content::class,
                    'content_id' => $content->id,
                    'qualified_metric' => $metric['seconds'],
                    'amount_minor' => $amount,
                    'eligibility_status' => 'creator_eligible',
                    'idempotency_key' => "subscription-settlement:{$period}:{$user->id}:".class_basename($content).":{$content->id}",
                    'calculation_snapshot' => [
                        'period' => $period,
                        'metric' => 'qualified_watch_seconds',
                        'creator_metric' => $metric['seconds'],
                        'total_metric' => $totalMetric,
                        'pool_minor' => $poolMinor,
                    ],
                ]);
            }

            $settlement->update([
                'eligible_revenue_minor' => $eligibleRevenue,
                'deductions_minor' => 0,
                'creator_pool_bps' => $poolBps,
                'creator_pool_minor' => $poolMinor,
                'metric' => 'qualified_watch_seconds',
                'total_qualified_metric' => $totalMetric,
                'creator_count' => collect($metrics)->pluck('user.id')->unique()->count(),
                'allocated_minor' => $allocated,
                'discrepancy_minor' => $poolMinor - $allocated,
                'status' => 'under_review',
                'calculation_snapshot' => [
                    'qualified_watch_seconds' => $threshold,
                    'max_qualified_plays_per_user_title_day' => $dailyCap,
                    'self_play_excluded' => true,
                    'subscription_access_required' => true,
                    'generated_code_version' => 1,
                ],
                'generated_at' => now(),
            ]);

            return $settlement->refresh();
        });
    }

    public function approve(CreatorSettlement $settlement, User $admin): void
    {
        if (! $admin->isAdmin() || $settlement->status !== 'under_review') {
            throw ValidationException::withMessages(['settlement' => ['Only an administrator can approve a reviewed settlement.']]);
        }
        if ((int) $settlement->discrepancy_minor !== 0) {
            throw ValidationException::withMessages([
                'settlement' => ['Resolve the settlement allocation discrepancy before approval.'],
            ]);
        }
        $settlement->update(['status' => 'approved', 'approved_by' => $admin->id, 'approved_at' => now()]);
    }

    public function finalize(CreatorSettlement $settlement, User $admin): void
    {
        if (! $admin->isAdmin() || $settlement->status !== 'approved') {
            throw ValidationException::withMessages(['settlement' => ['Approve the settlement before finalizing it.']]);
        }

        DB::transaction(function () use ($settlement, $admin) {
            $locked = CreatorSettlement::query()->lockForUpdate()->findOrFail($settlement->id);
            if ($locked->status === 'finalized') {
                return;
            }
            $creditedByUser = [];
            foreach ($locked->allocations()->where('eligibility_status', 'creator_eligible')->get() as $allocation) {
                if (! $allocation->user_id || $allocation->amount_minor <= 0) {
                    continue;
                }
                $user = User::find($allocation->user_id);
                if (! $user) {
                    continue;
                }
                $this->wallets->post(
                    $user,
                    (int) $allocation->amount_minor,
                    'subscription_settlement',
                    'available',
                    $allocation->idempotency_key,
                    CreatorSettlementAllocation::class,
                    $allocation->id,
                    'Finalized subscription creator-pool allocation.',
                    $allocation->calculation_snapshot ?? [],
                    $admin
                );
                $creditedByUser[$user->id] = ($creditedByUser[$user->id] ?? 0) + (int) $allocation->amount_minor;
            }
            $locked->update([
                'status' => 'finalized',
                'finalized_by' => $admin->id,
                'finalized_at' => now(),
            ]);
            $this->audit->record('creator.settlement_finalized', $admin, null, $locked);
            foreach ($creditedByUser as $userId => $amountMinor) {
                $user = User::find($userId);
                if ($user) {
                    $message = number_format($amountMinor).' UGX from the '.$locked->period.' creator pool is now available.';
                    $this->communications->notify(
                        $user,
                        "creator-settlement:{$locked->id}:{$userId}:finalized",
                        'creator_settlement_finalized',
                        'Subscription earnings finalized',
                        $message,
                        '/creator/finance/earnings',
                        ['message' => $message],
                        'creator_earnings'
                    );
                }
            }
        });
    }

    public function cancel(CreatorSettlement $settlement, User $admin, string $reason): void
    {
        if (! $admin->isAdmin() || ! in_array($settlement->status, ['draft', 'calculating', 'under_review'], true)) {
            throw ValidationException::withMessages([
                'settlement' => ['Only a draft or review-stage settlement may be cancelled.'],
            ]);
        }

        $settlement->update([
            'status' => 'cancelled',
            'failure_reason' => $reason,
            'cancelled_at' => now(),
            'cancelled_by' => $admin->id,
        ]);
        $this->audit->record(
            'creator.settlement_cancelled',
            $admin,
            null,
            $settlement,
            [],
            ['reason' => $reason]
        );
    }

    private function viewerHadSubscription(PlaybackSession $session, CarbonImmutable $start, CarbonImmutable $end): bool
    {
        $at = $session->started_at ?: $start;

        return UserSubscription::where('user_id', $session->user_id)
            ->where('started_at', '<=', $at)
            ->where('expires_at', '>=', $at)
            ->whereIn('status', ['ACTIVE', 'EXPIRED'])
            ->exists();
    }

    private function subscriptionEligible(Movie|TVShow $content): bool
    {
        $setting = CreatorMonetizationSetting::where('content_type', $content::class)
            ->where('content_id', $content->id)
            ->first();

        return (bool) $setting?->subscription_enabled
            && $setting->status === 'approved'
            && $content->is_active
            && $content->publication_status === 'published';
    }

    private function eligibleCreator(Movie|TVShow $content): ?User
    {
        $profile = $content->media_library_id
            ? ($content->mediaLibrary ?? MediaLibrary::find($content->media_library_id))
            : ($content->vj ?? VJ::find($content->vj_id));
        if (! $profile?->user_id || ! $profile->is_verified) {
            return null;
        }
        $permission = CreatorPermission::where('user_id', $profile->user_id)->first();
        if (! $permission?->identity_verified
            || ! $permission?->monetization_enabled
            || $permission?->is_suspended
            || $permission?->is_revoked) {
            return null;
        }

        return $profile->user;
    }
}
