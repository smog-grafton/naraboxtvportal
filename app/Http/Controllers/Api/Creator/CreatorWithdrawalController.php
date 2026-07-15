<?php

namespace App\Http\Controllers\Api\Creator;

use App\Models\CreatorPayoutMethod;
use App\Models\CreatorWithdrawalRequest;
use App\Services\WithdrawalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreatorWithdrawalController extends CreatorBaseController
{
    public function __construct(
        private WithdrawalService $withdrawalService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->isCreator() && !$user->isAdmin()) {
            return $this->notCreator();
        }

        $query = CreatorWithdrawalRequest::where('user_id', $user->id)
            ->with('payoutMethod')
            ->orderByDesc('requested_at');

        $perPage = min((int) $request->get('per_page', 15), 50);
        $paginator = $query->paginate($perPage);

        $items = $paginator->getCollection()->map(fn ($w) => $this->formatWithdrawal($w));

        return response()->json([
            'success' => true,
            'data' => [
                'withdrawals' => $items,
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->isCreator() && !$user->isAdmin()) {
            return $this->notCreator();
        }

        $validated = $request->validate([
            'payout_method_id' => ['required', 'integer', 'exists:creator_payout_methods,id'],
            'amount' => ['required', 'numeric', 'min:1'],
        ]);

        $payoutMethod = CreatorPayoutMethod::forUser($user->id)->findOrFail($validated['payout_method_id']);

        try {
            $withdrawal = $this->withdrawalService->requestWithdrawal(
                $user,
                $payoutMethod,
                (float) $validated['amount']
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => ['withdrawal' => $this->formatWithdrawal($withdrawal->load('payoutMethod'))],
        ], 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->isCreator() && !$user->isAdmin()) {
            return $this->notCreator();
        }

        $withdrawal = CreatorWithdrawalRequest::where('user_id', $user->id)->findOrFail($id);

        try {
            $this->withdrawalService->cancel($withdrawal);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json(['success' => true]);
    }

    private function formatWithdrawal(CreatorWithdrawalRequest $w): array
    {
        $method = $w->relationLoaded('payoutMethod') ? $w->payoutMethod : $w->payoutMethod;
        $methodDisplay = $method
            ? ($method->method_type === 'mobile_money'
                ? ($method->masked_phone ?? 'Mobile Money')
                : ($method->bank_name . ' ****' . substr($method->account_number ?? '', -4)))
            : null;

        return [
            'id' => $w->id,
            'amount' => (float) $w->amount,
            'status' => $w->status,
            'reference' => $w->reference,
            'method_display' => $methodDisplay,
            'requested_at' => $w->requested_at?->toIso8601String(),
            'processed_at' => $w->processed_at?->toIso8601String(),
            'failure_reason' => $w->failure_reason,
        ];
    }
}
