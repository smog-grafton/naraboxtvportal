<?php

namespace App\Http\Controllers\Api\Creator;

use App\Models\CreatorContentSubmission;
use App\Models\CreatorSupportRequest;
use App\Models\CreatorWithdrawalRequest;
use App\Models\Episode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CreatorSupportController extends CreatorBaseController
{
    public function index(Request $request): JsonResponse
    {
        if (! $this->creatorAccessAllowed($request->user())) {
            return $this->notCreator();
        }

        $tickets = CreatorSupportRequest::where('user_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $tickets->items(),
            'meta' => [
                'current_page' => $tickets->currentPage(),
                'last_page' => $tickets->lastPage(),
                'total' => $tickets->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if (! $this->creatorAccessAllowed($request->user())) {
            return $this->notCreator();
        }

        $validated = $request->validate([
            'category' => ['required', Rule::in(config('creator.support_categories', []))],
            'subject' => ['required', 'string', 'min:5', 'max:255'],
            'message' => ['required', 'string', 'min:10', 'max:10000'],
            'submission_id' => ['nullable', 'uuid'],
            'movie_id' => ['nullable', 'integer'],
            'tv_show_id' => ['nullable', 'integer'],
            'episode_id' => ['nullable', 'integer'],
            'withdrawal_id' => ['nullable', 'integer'],
        ]);
        $user = $request->user();

        if (! empty($validated['submission_id'])) {
            CreatorContentSubmission::where('user_id', $user->id)->findOrFail($validated['submission_id']);
        }
        if (! empty($validated['movie_id'])) {
            $this->creatorMovieQuery($user)->findOrFail($validated['movie_id']);
        }
        if (! empty($validated['tv_show_id'])) {
            $this->creatorTvShowQuery($user)->findOrFail($validated['tv_show_id']);
        }
        if (! empty($validated['episode_id'])) {
            $episode = Episode::with('season')->findOrFail($validated['episode_id']);
            abort_unless($episode->season && $this->creatorTvShowQuery($user)->whereKey($episode->season->tv_show_id)->exists(), 404);
        }
        if (! empty($validated['withdrawal_id'])) {
            CreatorWithdrawalRequest::where('user_id', $user->id)->findOrFail($validated['withdrawal_id']);
        }

        $ticket = CreatorSupportRequest::create(array_merge($validated, [
            'user_id' => $user->id,
            'status' => 'open',
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Your support request was sent with the relevant creator context attached.',
            'data' => $ticket,
        ], 201);
    }
}
