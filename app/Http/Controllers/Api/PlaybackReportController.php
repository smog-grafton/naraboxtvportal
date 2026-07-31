<?php

namespace App\Http\Controllers\Api;

use App\Events\PlaybackIssueReported;
use App\Http\Controllers\Controller;
use App\Services\PlaybackHealthService;
use Illuminate\Http\Request;

class PlaybackReportController extends Controller
{
    public function store(Request $request, PlaybackHealthService $playbackHealthService)
    {
        $user = $request->user();

        $validated = $request->validate([
            'media_type' => 'required|in:movie,show,episode,MOVIE,TV_SHOW,EPISODE',
            'media_id' => 'required|integer|min:1',
            'episode_id' => 'nullable|integer|exists:episodes,id',
            'source_id' => 'nullable|integer|min:1',
            'source_role' => 'nullable|string|max:48',
            'server_key' => 'nullable|string|max:64',
            'source_format' => 'nullable|string|max:24',
            'playback_session_id' => 'nullable|integer|exists:playback_sessions,id',
            'error_type' => 'required|in:not_found,timeout,buffering,playback_failed,manifest_failed,fallback_succeeded,unknown,slow_start',
            'error_message' => 'nullable|string|max:1000',
            'http_status' => 'nullable|integer|min:100|max:599',
            'player_error_code' => 'nullable|string|max:96',
            'playback_url' => 'nullable|url|max:2048',
            'device' => 'nullable|in:web,android,ios,tv',
            'platform' => 'nullable|string|max:32',
            'app_version' => 'nullable|string|max:64',
            'attempt_number' => 'nullable|integer|min:1|max:5',
            'fallback_source_id' => 'nullable|integer|min:1',
            'fallback_succeeded' => 'nullable|boolean',
            'startup_time_ms' => 'nullable|integer|min:0',
            'load_time_ms' => 'nullable|integer|min:0',
            'buffering_count' => 'nullable|integer|min:0',
            'buffering_duration_ms' => 'nullable|integer|min:0',
        ]);

        $report = $playbackHealthService->createReport(array_merge($validated, [
            'user_id' => $user?->id,
        ]));

        event(new PlaybackIssueReported($report));

        return response()->json([
            'success' => true,
            'data' => $report,
        ], 201);
    }
}
