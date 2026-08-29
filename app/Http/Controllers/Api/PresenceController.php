<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PresenceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PresenceController extends Controller
{
    public function heartbeat(Request $request, PresenceService $presenceService)
    {
        $validated = $request->validate([
            'presence_id' => ['required', 'string', 'min:8', 'max:128'],
            'platform' => ['required', 'string', 'max:32'],
            'current_path' => ['nullable', 'string', 'max:255'],
            'app_version' => ['nullable', 'string', 'max:64'],
        ]);

        $user = $request->user();

        if (! $user) {
            try {
                $user = Auth::guard('sanctum')->user();
            } catch (\Throwable) {
                $user = null;
            }
        }

        return response()->json([
            'success' => true,
            'data' => $presenceService->heartbeat($validated, $user?->id),
        ])->header('Cache-Control', 'no-store');
    }
}
