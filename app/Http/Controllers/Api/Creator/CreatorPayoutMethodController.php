<?php

namespace App\Http\Controllers\Api\Creator;

use App\Models\CreatorPayoutMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CreatorPayoutMethodController extends CreatorBaseController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $this->creatorAccessAllowed($user)) {
            return $this->notCreator();
        }

        $methods = CreatorPayoutMethod::forUser($user->id)->get();

        $items = $methods->map(fn ($m) => $this->formatPayoutMethod($m));

        return response()->json([
            'success' => true,
            'data' => ['payout_methods' => $items],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $this->creatorAccessAllowed($user)) {
            return $this->notCreator();
        }

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

        $validated['user_id'] = $user->id;
        $validated['is_default'] = $request->boolean('is_default', false);

        if ($validated['is_default']) {
            CreatorPayoutMethod::forUser($user->id)->update(['is_default' => false]);
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

        return response()->json([
            'success' => true,
            'data' => ['payout_method' => $this->formatPayoutMethod($method)],
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (! $this->creatorAccessAllowed($user)) {
            return $this->notCreator();
        }

        $method = CreatorPayoutMethod::forUser($user->id)->findOrFail($id);

        $validated = $request->validate([
            'provider' => ['nullable', 'string', 'max:50'],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'account_name' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:50'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_code' => ['nullable', 'string', 'max:20'],
            'is_default' => ['boolean'],
        ]);

        if (!empty($validated['is_default'])) {
            CreatorPayoutMethod::forUser($user->id)->where('id', '!=', $id)->update(['is_default' => false]);
        }

        $protected = $method->protected_details ?? [];
        foreach (['phone_number', 'account_name', 'account_number'] as $key) {
            if (array_key_exists($key, $validated)) {
                $protected[$key] = $validated[$key];
                unset($validated[$key]);
            }
        }
        $method->update(array_merge($validated, [
            'protected_details' => $protected,
            'details_fingerprint' => hash_hmac('sha256', json_encode($protected), (string) config('app.key')),
            'is_verified' => false,
            'verification_status' => 'pending',
            'verified_by' => null,
            'verified_at' => null,
            'changed_at' => now(),
            'withdrawal_hold_until' => now()->addHours((int) config('creator.payout_change_hold_hours', 48)),
        ]));

        return response()->json([
            'success' => true,
            'data' => ['payout_method' => $this->formatPayoutMethod($method->fresh())],
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (! $this->creatorAccessAllowed($user)) {
            return $this->notCreator();
        }

        $method = CreatorPayoutMethod::forUser($user->id)->findOrFail($id);
        $method->delete();

        return response()->json(['success' => true]);
    }

    public function setDefault(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (! $this->creatorAccessAllowed($user)) {
            return $this->notCreator();
        }

        $method = CreatorPayoutMethod::forUser($user->id)->findOrFail($id);
        CreatorPayoutMethod::forUser($user->id)->update(['is_default' => false]);
        $method->update(['is_default' => true]);

        return response()->json([
            'success' => true,
            'data' => ['payout_method' => $this->formatPayoutMethod($method->fresh())],
        ]);
    }

    private function formatPayoutMethod(CreatorPayoutMethod $m): array
    {
        return [
            'id' => $m->id,
            'method_type' => $m->method_type,
            'provider' => $m->provider,
            'phone_number_masked' => $m->method_type === 'mobile_money' ? $m->masked_phone : null,
            'account_name' => $m->masked_account_name,
            'account_number_masked' => $m->method_type === 'bank' ? $m->masked_account : null,
            'bank_name' => $m->bank_name,
            'is_default' => (bool) $m->is_default,
            'is_verified' => (bool) $m->is_verified,
            'verification_status' => $m->verification_status ?? ($m->is_verified ? 'verified' : 'pending'),
            'withdrawal_hold_until' => $m->withdrawal_hold_until?->toIso8601String(),
            'created_at' => $m->created_at?->toIso8601String(),
        ];
    }
}
