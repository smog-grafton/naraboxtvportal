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
        if (!$user->isCreator() && !$user->isAdmin()) {
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
        if (!$user->isCreator() && !$user->isAdmin()) {
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

        $method = CreatorPayoutMethod::create($validated);

        return response()->json([
            'success' => true,
            'data' => ['payout_method' => $this->formatPayoutMethod($method)],
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->isCreator() && !$user->isAdmin()) {
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

        $method->update($validated);

        return response()->json([
            'success' => true,
            'data' => ['payout_method' => $this->formatPayoutMethod($method->fresh())],
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->isCreator() && !$user->isAdmin()) {
            return $this->notCreator();
        }

        $method = CreatorPayoutMethod::forUser($user->id)->findOrFail($id);
        $method->delete();

        return response()->json(['success' => true]);
    }

    public function setDefault(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->isCreator() && !$user->isAdmin()) {
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
            'account_name' => $m->account_name,
            'account_number_masked' => $m->method_type === 'bank' ? $m->masked_account : null,
            'bank_name' => $m->bank_name,
            'is_default' => (bool) $m->is_default,
            'is_verified' => (bool) $m->is_verified,
            'created_at' => $m->created_at?->toIso8601String(),
        ];
    }
}
