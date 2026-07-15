<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class MarketingPreferenceController extends Controller
{
    public function unsubscribe(Request $request)
    {
        $validated = $request->validate([
            'token' => 'required|string',
        ]);

        $user = User::query()->where('marketing_opt_in_token', $validated['token'])->first();

        if (! $user) {
            return response()->json(['message' => 'Invalid unsubscribe token.'], 404);
        }

        $user->update(['marketing_emails_enabled' => false]);

        return response()->json([
            'success' => true,
            'message' => 'You have been unsubscribed from promotional emails.',
        ]);
    }
}
