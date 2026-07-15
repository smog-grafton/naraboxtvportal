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
        if (!$user->isCreator() && !$user->isAdmin()) {
            return $this->notCreator();
        }

        $balance = $this->earningsService->getBalance($user);
        $settings = \App\Models\FinancialSetting::current();

        return response()->json([
            'success' => true,
            'data' => [
                'balance' => $balance,
                'min_withdrawal_amount' => $settings ? (float) $settings->min_withdrawal_amount : 10000,
            ],
        ]);
    }

    public function earnings(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->isCreator() && !$user->isAdmin()) {
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
}
