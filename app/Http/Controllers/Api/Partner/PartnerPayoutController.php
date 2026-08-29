<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Controller;
use App\Models\CreatorPayoutMethod;
use App\Models\CreatorWithdrawalRequest;
use App\Models\Partner;
use App\Services\PartnerWithdrawalService;
use App\Services\IoTeCService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PartnerPayoutController extends Controller
{
    public function __construct(private readonly PartnerWithdrawalService $withdrawals)
    {
    }

    public function methods(Request $request): JsonResponse
    {
        $partner = $this->partner($request);
        if (! $partner) return $this->denied();

        return response()->json(['success' => true, 'data' => [
            'payout_methods' => CreatorPayoutMethod::forUser($partner->user_id)->get()->map(fn (CreatorPayoutMethod $method) => $this->formatMethod($method)),
        ]]);
    }

    public function storeMethod(Request $request): JsonResponse
    {
        $partner = $this->partner($request);
        if (! $partner) return $this->denied();

        $validated = $request->validate([
            'method_type' => ['required', Rule::in(['mobile_money', 'bank'])],
            'provider' => ['nullable', 'string', 'max:50'],
            'phone_number' => ['required_if:method_type,mobile_money', 'nullable', 'string', 'max:20'],
            'account_name' => ['required_if:method_type,bank', 'nullable', 'string', 'max:255'],
            'account_number' => ['required_if:method_type,bank', 'nullable', 'string', 'max:50'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_code' => ['nullable', 'string', 'max:20'],
            'is_default' => ['boolean'],
        ]);
        $validated['user_id'] = $partner->user_id;
        $validated['is_default'] = $request->boolean('is_default');
        if ($validated['method_type'] === 'mobile_money') {
            $phone = (string) ($validated['phone_number'] ?? '');
            if (! IoTeCService::validatePhone($phone)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'phone_number' => ['Enter a valid Uganda mobile money number.'],
                ]);
            }
            $validated['phone_number'] = IoTeCService::normalizePhone($phone);
        }
        if ($validated['is_default']) {
            CreatorPayoutMethod::forUser($partner->user_id)->update(['is_default' => false]);
        }
        $protected = [
            'phone_number' => $validated['phone_number'] ?? null,
            'account_name' => $validated['account_name'] ?? null,
            'account_number' => $validated['account_number'] ?? null,
        ];
        unset($validated['phone_number'], $validated['account_name'], $validated['account_number']);
        $method = CreatorPayoutMethod::create(array_merge($validated, [
            'protected_details' => $protected,
            'details_fingerprint' => hash_hmac('sha256', json_encode($protected), (string) config('app.key')),
            'is_verified' => false,
            'verification_status' => 'pending',
            'changed_at' => now(),
        ]));

        return response()->json(['success' => true, 'data' => ['payout_method' => $this->formatMethod($method)]], 201);
    }

    public function withdrawals(Request $request): JsonResponse
    {
        $partner = $this->partner($request);
        if (! $partner) return $this->denied();

        $query = CreatorWithdrawalRequest::query()
            ->where('user_id', $partner->user_id)
            ->where('beneficiary_type', 'partner')
            ->with('payoutMethod')
            ->latest('requested_at');
        $paginator = $query->paginate(min(50, max(1, (int) $request->query('per_page', 15))));

        return response()->json(['success' => true, 'data' => [
            'withdrawals' => $paginator->getCollection()->map(fn (CreatorWithdrawalRequest $withdrawal) => $this->formatWithdrawal($withdrawal)),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]]);
    }

    public function requestWithdrawal(Request $request): JsonResponse
    {
        $partner = $this->partner($request);
        if (! $partner) return $this->denied();

        $validated = $request->validate([
            'payout_method_id' => ['required', 'integer', 'exists:creator_payout_methods,id'],
            'amount' => ['required', 'regex:/^\d+$/'],
        ]);
        $method = CreatorPayoutMethod::forUser($partner->user_id)->findOrFail($validated['payout_method_id']);

        try {
            $withdrawal = $this->withdrawals->requestWithdrawal(
                $partner->user,
                $partner,
                $method,
                $validated['amount'],
                $request->header('Idempotency-Key')
            );
        } catch (\Illuminate\Validation\ValidationException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage(), 'errors' => $exception->errors()], 422);
        }

        return response()->json(['success' => true, 'data' => ['withdrawal' => $this->formatWithdrawal($withdrawal->load('payoutMethod'))]], 201);
    }

    public function cancelWithdrawal(Request $request, int $id): JsonResponse
    {
        $partner = $this->partner($request);
        if (! $partner) return $this->denied();

        $withdrawal = CreatorWithdrawalRequest::query()
            ->where('user_id', $partner->user_id)
            ->where('beneficiary_type', 'partner')
            ->findOrFail($id);
        app(\App\Services\WithdrawalService::class)->cancel($withdrawal);

        return response()->json(['success' => true]);
    }

    private function partner(Request $request): ?Partner
    {
        $partner = $request->user()?->partnerProfile;

        return $partner && $partner->isActiveAt() ? $partner->load('user') : null;
    }

    private function denied(): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Partner access is not active.'], 403);
    }

    private function formatMethod(CreatorPayoutMethod $method): array
    {
        return [
            'id' => $method->id,
            'method_type' => $method->method_type,
            'provider' => $method->provider,
            'phone_number_masked' => $method->method_type === 'mobile_money' ? $method->masked_phone : null,
            'account_name' => $method->masked_account_name,
            'account_number_masked' => $method->method_type === 'bank' ? $method->masked_account : null,
            'bank_name' => $method->bank_name,
            'is_default' => (bool) $method->is_default,
            'is_verified' => (bool) $method->is_verified,
            'verification_status' => $method->verification_status ?? ($method->is_verified ? 'verified' : 'pending'),
            'withdrawal_hold_until' => $method->withdrawal_hold_until?->toIso8601String(),
        ];
    }

    private function formatWithdrawal(CreatorWithdrawalRequest $withdrawal): array
    {
        return [
            'id' => $withdrawal->id,
            'amount_minor' => (int) ($withdrawal->amount_minor ?? 0),
            'currency' => $withdrawal->currency ?? 'UGX',
            'status' => $withdrawal->status,
            'reference' => $withdrawal->reference,
            'method_display' => $withdrawal->payoutMethod?->method_type === 'mobile_money'
                ? ($withdrawal->payoutMethod?->masked_phone ?? 'Mobile Money')
                : ($withdrawal->payoutMethod?->masked_account ?? 'Bank'),
            'requested_at' => $withdrawal->requested_at?->toIso8601String(),
            'processed_at' => $withdrawal->processed_at?->toIso8601String(),
            'failure_reason' => $withdrawal->failure_reason,
            'gateway' => $withdrawal->gateway_used,
            'gateway_reference' => $withdrawal->gateway_reference,
            'provider_status' => $withdrawal->provider_status,
            'last_reconciled_at' => $withdrawal->last_reconciled_at?->toIso8601String(),
        ];
    }
}
