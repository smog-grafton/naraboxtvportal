<?php

namespace App\Http\Controllers\Api;

use App\Events\ContentRequested;
use App\Http\Controllers\Controller;
use App\Models\ContentRequest;
use Illuminate\Http\Request;

class ContentRequestController extends Controller
{
    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|in:movie,show,episode,other',
            'message' => 'nullable|string|max:5000',
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'requested_from' => 'nullable|in:web,android,ios,tv,admin',
            'notify_on_status_change' => 'nullable|boolean',
        ]);

        if (! $user && empty($validated['email']) && empty($validated['name'])) {
            return response()->json([
                'message' => 'Name or email is required for guest requests.',
            ], 422);
        }

        $contentRequest = ContentRequest::create([
            'user_id' => $user?->id,
            'name' => $user?->name ?? ($validated['name'] ?? null),
            'email' => $user?->email ?? ($validated['email'] ?? null),
            'title' => trim($validated['title']),
            'type' => $validated['type'],
            'message' => isset($validated['message']) ? strip_tags($validated['message']) : null,
            'status' => 'pending',
            'requested_from' => $validated['requested_from'] ?? 'web',
            'notify_on_status_change' => $validated['notify_on_status_change'] ?? true,
        ]);

        event(new ContentRequested($contentRequest));

        return response()->json([
            'success' => true,
            'data' => $contentRequest,
        ], 201);
    }

    public function mine(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return response()->json([
            'data' => ContentRequest::query()
                ->where('user_id', $user->id)
                ->latest()
                ->get(),
        ]);
    }
}
