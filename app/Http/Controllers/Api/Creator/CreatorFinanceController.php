<?php

namespace App\Http\Controllers\Api\Creator;

use App\Services\CreatorEarningsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreatorFinanceController extends CreatorBaseController
{
    public function __construct(
        private CreatorEarningsService $earningsService
    ) {
    }

    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $this->creatorAccessAllowed($user)) {
            return $this->notCreator();
        }

        $balance = $this->earningsService->getBalance($user);
        $settings = \App\Models\FinancialSetting::current();

        return response()->json([
            'success' => true,
            'data' => [
                'balance' => $balance,
                'min_withdrawal_amount' => $settings ? (float) $settings->min_withdrawal_amount : 10000,
                'min_withdrawal_minor' => (int) ($settings?->min_withdrawal_minor ?? 10000),
                'max_withdrawal_minor' => $settings?->max_withdrawal_minor !== null ? (int) $settings->max_withdrawal_minor : null,
                'creator_share_bps' => (int) ($settings?->creator_share_bps ?? 7000),
                'subscription_pool_bps' => (int) ($settings?->subscription_pool_bps ?? 4000),
            ],
        ]);
    }

    public function earnings(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $this->creatorAccessAllowed($user)) {
            return $this->notCreator();
        }

        $query = \App\Models\CreatorEarning::forUser($user->id)
            ->with(['transaction', 'earnable'])
            ->orderByDesc('created_at');

        $perPage = min((int) $request->get('per_page', 15), 50);
        $paginator = $query->paginate($perPage);

        $items = $paginator->getCollection()->map(function ($earning) {
            $title = null;
            if ($earning->earnable) {
                $title = $earning->earnable->title ?? null;
            }
            return [
                'id' => $earning->id,
                'transaction_ref' => $earning->transaction?->transaction_ref,
                'title' => $title,
                'gross_amount' => (float) $earning->gross_amount,
                'commission_rate' => (float) $earning->commission_rate,
                'creator_amount' => (float) $earning->creator_amount,
                'platform_amount' => (float) $earning->platform_amount,
                'gross_amount_minor' => (int) ($earning->gross_amount_minor ?? $earning->gross_amount),
                'creator_amount_minor' => (int) ($earning->creator_amount_minor ?? $earning->creator_amount),
                'platform_amount_minor' => (int) ($earning->platform_amount_minor ?? $earning->platform_amount),
                'creator_share_bps' => $earning->creator_share_bps,
                'eligibility_status' => $earning->eligibility_status,
                'status' => $earning->status,
                'available_at' => $earning->available_at?->toIso8601String(),
                'created_at' => $earning->created_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'earnings' => $items,
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
            ],
        ]);
    }

    public function ledger(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $this->creatorAccessAllowed($user)) {
            return $this->notCreator();
        }

        $wallet = \App\Models\CreatorWallet::firstOrCreate(
            ['user_id' => $user->id],
            ['currency' => 'UGX', 'status' => 'active']
        );
        $paginator = $wallet->entries()
            ->latest('id')
            ->paginate(min(max((int) $request->integer('per_page', 20), 1), 50));

        return response()->json([
            'success' => true,
            'data' => [
                'entries' => $paginator->getCollection()->map(fn ($entry) => [
                    'id' => $entry->id,
                    'amount_minor' => (int) $entry->amount_minor,
                    'currency' => $entry->currency,
                    'entry_type' => $entry->entry_type,
                    'bucket' => $entry->bucket,
                    'description' => $entry->description,
                    'available_at' => $entry->available_at?->toIso8601String(),
                    'created_at' => $entry->created_at?->toIso8601String(),
                ]),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'total' => $paginator->total(),
                ],
            ],
        ]);
    }
}
