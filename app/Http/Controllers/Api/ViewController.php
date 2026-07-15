<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Movie;
use Illuminate\Http\Request;

/**
 * @group Access & Views
 *
 * Track play views. Body: media_id, media_type (MOVIE|TV_SHOW). Public.
 */
class ViewController extends Controller
{
    /**
     * Track a view. Body: media_id, media_type (MOVIE|TV_SHOW).
     */
    public function track(Request $request)
    {
        $request->validate([
            'media_id' => 'required|integer',
            'media_type' => 'required|in:MOVIE,TV_SHOW',
        ]);

        $mediaId = $request->media_id;
        $mediaType = $request->media_type;

        // Find the movie/TV show
        $movie = Movie::where('id', $mediaId)
            ->where('media_type', $mediaType === 'TV_SHOW' ? 'SERIES' : 'MOVIE')
            ->where('is_active', true)
            ->first();

        if (!$movie) {
            return response()->json(['error' => 'Media not found'], 404);
        }

        // Increment views_count (not manual_views - that's for admin manipulation)
        $movie->increment('views_count');

        return response()->json([
            'message' => 'View tracked successfully',
            'views_count' => $movie->views_count + $movie->manual_views,
        ]);
    }
}
