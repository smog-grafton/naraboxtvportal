<?php

namespace App\Services;

use App\Models\CreatorEarning;
use App\Models\CreatorWithdrawalAllocation;
use App\Models\CreatorWithdrawalRequest;
use App\Models\FinancialSetting;
use App\Models\MediaLibrary;
use App\Models\Movie;
use App\Models\PaymentTransaction;
use App\Models\TVShow;
use App\Models\VJ;

class CreatorEarningsService
{
    /**
     * Allocate creator earnings from a RENT or BUY payment transaction.
     * Resolves creator from movie->vj->user or movie->mediaLibrary->user.
     */
    public function allocateFromTransaction(PaymentTransaction $transaction): ?CreatorEarning
    {
        if (!in_array($transaction->type, ['RENT', 'BUY'])) {
            return null;
        }

        $existing = CreatorEarning::where('transaction_id', $transaction->id)->first();
        if ($existing) {
            return $existing;
        }

        $transactionable = $transaction->transactionable;
        if (!$transactionable) {
            return null;
        }

        [$creatorUser, $isVerified] = $this->resolveCreator($transactionable);
        if (!$creatorUser) {
            return null;
        }

        $settings = FinancialSetting::current();
        if (!$settings) {
            return null;
        }

        $commissionRate = (float) $settings->commission_rate / 100;
        $holdDays = (int) $settings->creator_hold_days;

        if (!$settings->unverified_creator_earns && !$isVerified) {
            $creatorShare = 0;
        } else {
            $creatorShare = $transaction->amount * (1 - $commissionRate);
        }

        $platformFee = $transaction->amount * $commissionRate;
        $availableAt = now()->addDays($holdDays);

        return CreatorEarning::create([
            'user_id' => $creatorUser->id,
            'transaction_id' => $transaction->id,
            'earnable_type' => get_class($transactionable),
            'earnable_id' => $transactionable->id,
            'gross_amount' => $transaction->amount,
            'commission_rate' => $settings->commission_rate,
            'platform_amount' => $platformFee,
            'creator_amount' => $creatorShare,
            'status' => 'pending',
            'available_at' => $availableAt,
        ]);
    }

    /**
     * Resolve creator user and verification status from Movie or TVShow.
     * @return array{0: \App\Models\User|null, 1: bool}
     */
    private function resolveCreator(Movie|TVShow $media): array
    {
        // Prefer media library (creator portal) over VJ
        if ($media->media_library_id) {
            $library = $media->mediaLibrary ?? MediaLibrary::find($media->media_library_id);
            if ($library?->user_id) {
                return [$library->user, (bool) $library->is_verified];
            }
        }

        if ($media->vj_id) {
            $vj = $media->vj ?? VJ::find($media->vj_id);
            if ($vj?->user_id) {
                // VJs are considered verified when linked to a user
                return [$vj->user, true];
            }
        }

        return [null, false];
    }

    /**
     * Get balance summary for a creator.
     * Available = sum(creator_amount) for available earnings minus amounts allocated to in-flight withdrawals.
     * @return array{pending: float, available: float, withdrawn_total: float, total_earned: float}
     */
    public function getBalance(\App\Models\User $user): array
    {
        $earnings = CreatorEarning::forUser($user->id)->get();
        $pending = $earnings->where('status', 'pending')->sum('creator_amount');
        $totalEarned = $earnings->sum('creator_amount');

        $inFlightStatuses = [
            CreatorWithdrawalRequest::STATUS_PENDING,
            CreatorWithdrawalRequest::STATUS_UNDER_REVIEW,
            CreatorWithdrawalRequest::STATUS_APPROVED,
            CreatorWithdrawalRequest::STATUS_PROCESSING,
        ];
        $allocatedInFlight = CreatorWithdrawalAllocation::whereHas('withdrawalRequest', function ($q) use ($inFlightStatuses) {
            $q->whereIn('status', $inFlightStatuses);
        })->whereHas('creatorEarning', fn ($q) => $q->where('user_id', $user->id))->sum('amount');

        $availableRaw = $earnings->where('status', 'available')->sum('creator_amount');
        $available = max(0, (float) $availableRaw - (float) $allocatedInFlight);

        $withdrawn = CreatorWithdrawalAllocation::whereHas('withdrawalRequest', fn ($q) => $q->where('status', CreatorWithdrawalRequest::STATUS_PAID))
            ->whereHas('creatorEarning', fn ($q) => $q->where('user_id', $user->id))
            ->sum('amount');

        return [
            'pending' => (float) $pending,
            'available' => $available,
            'withdrawn_total' => (float) $withdrawn,
            'total_earned' => (float) $totalEarned,
        ];
    }

    /**
     * Move pending earnings to available when available_at has passed.
     * Call from scheduler (e.g. daily).
     */
    public function markAvailable(): int
    {
        return CreatorEarning::pending()
            ->where('available_at', '<=', now())
            ->update(['status' => 'available']);
    }
}
