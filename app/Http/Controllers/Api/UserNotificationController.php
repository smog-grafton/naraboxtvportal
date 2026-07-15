<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserNotification;
use Illuminate\Http\Request;

class UserNotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $notifications = UserNotification::query()
            ->forUser($user)
            ->latest()
            ->limit((int) $request->integer('limit', 20))
            ->get();

        return response()->json([
            'data' => $notifications,
            'meta' => [
                'unread_count' => UserNotification::query()
                    ->forUser($user)
                    ->whereNull('read_at')
                    ->count(),
            ],
        ]);
    }

    public function markAsRead(Request $request, UserNotification $notification)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if (! $notification->is_global && $notification->user_id !== $user->id) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $notification->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }

    public function readAll(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        UserNotification::query()
            ->forUser($user)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }
}
