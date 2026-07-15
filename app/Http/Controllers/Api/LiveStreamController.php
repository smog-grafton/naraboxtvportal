<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LiveStream;
use Illuminate\Http\Request;

/**
 * @group Live Streams
 *
 * Live channels. List with optional type=live|archived; get by id.
 */
class LiveStreamController extends Controller
{
    /**
     * Get active live streams. Query: type (live|archived).
     */
    public function index(Request $request)
    {
        $query = LiveStream::where('is_active', true)
            ->orderBy('is_live', 'desc')
            ->orderBy('order', 'asc')
            ->orderBy('created_at', 'desc');

        // Filter by live/archived if requested
        if ($request->has('type')) {
            if ($request->type === 'live') {
                $query->where('is_live', true);
            } elseif ($request->type === 'archived') {
                $query->where('is_archived', true);
            }
        }

        $streams = $query->get();

        return response()->json([
            'data' => $streams->map(function ($stream) {
                return [
                    'id' => $stream->id,
                    'title' => $stream->title,
                    'description' => $stream->description,
                    'stream_url' => $stream->stream_url,
                    'platform' => $stream->platform,
                    'is_live' => $stream->is_live,
                    'is_archived' => $stream->is_archived,
                    'thumbnail' => $stream->thumbnail ? asset('storage/' . $stream->thumbnail) : null,
                    'viewer_count' => $stream->viewer_count,
                ];
            })
        ]);
    }

    /**
     * Get a specific live stream
     */
    public function show($id)
    {
        $stream = LiveStream::where('is_active', true)->findOrFail($id);

        return response()->json([
            'data' => [
                'id' => $stream->id,
                'title' => $stream->title,
                'description' => $stream->description,
                'stream_url' => $stream->stream_url,
                'platform' => $stream->platform,
                'is_live' => $stream->is_live,
                'is_archived' => $stream->is_archived,
                'thumbnail' => $stream->thumbnail ? asset('storage/' . $stream->thumbnail) : null,
                'viewer_count' => $stream->viewer_count,
            ]
        ]);
    }
}
