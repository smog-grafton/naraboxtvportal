<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VJ;
use Illuminate\Http\Request;

/**
 * @group VJs
 *
 * List and fetch VJs (translators/presenters). Filter by featured; order by rating or movies count.
 */
class VJController extends Controller
{
    /**
     * List VJs. Query: featured (1), order_by (movies_count), limit.
     */
    public function index(Request $request)
    {
        $query = VJ::where('is_active', true)
            ->with('genres');

        // Featured filter (?featured=1)
        if ($request->boolean('featured')) {
            $query->where('is_featured', true);
        }

        // Order by movie count if requested
        if ($request->has('order_by') && $request->get('order_by') === 'movies_count') {
            $query->withCount(['movies' => function ($q) {
                $q->where('is_active', true);
            }])
                  ->orderBy('movies_count', 'desc');
        } else {
            $query->orderBy('rating', 'desc');
        }

        $limit = $request->get('limit');
        if ($limit) {
            $vjs = $query->limit($limit)->get();
        } else {
            $vjs = $query->get();
        }

        $vjs = $vjs->map(function ($vj) {
            // Helper to get full URL for images
            $getImageUrl = function ($path) {
                if (empty($path)) return null;
                if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                    return $path;
                }
                return asset('storage/' . $path);
            };

            return [
                'id' => $vj->id,
                'name' => $vj->name,
                'slug' => $vj->slug,
                'image' => $getImageUrl($vj->image),
                'banner' => $getImageUrl($vj->banner),
                'rating' => (float) $vj->rating,
                'specialty' => $vj->genres->pluck('name')->toArray(),
                'bio' => $vj->bio,
                'translatedCount' => $vj->translated_count,
                'moviesCount' => $vj->movies_count ?? $vj->movies()->count(),
                'isVerified' => (bool) ($vj->is_verified ?? false),
            ];
        });

        return response()->json([
            'data' => $vjs,
        ]);
    }

    public function show($id)
    {
        // Support both slug and ID (backward compatibility). The VJ's movie
        // catalogue is intentionally NOT eager-loaded here: the archive grid
        // on the VJ profile page fetches its own paginated results via
        // MovieController::index / TVShowController::index (?vj=...), so
        // embedding every movie in this payload was dead weight that scaled
        // with catalogue size (was unbounded — no limit/pagination at all).
        $vj = VJ::where('is_active', true)
            ->with('genres')
            ->where(function ($query) use ($id) {
                $query->where('id', $id)
                      ->orWhere('slug', $id);
            })
            ->first();

        if (!$vj) {
            return response()->json(['message' => 'VJ not found'], 404);
        }

        // Helper to get full URL for images
        $getImageUrl = function ($path) {
            if (empty($path)) return null;
            if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                return $path;
            }
            return asset('storage/' . $path);
        };

        return response()->json([
            'id' => $vj->id,
            'slug' => $vj->slug,
            'name' => $vj->name,
            'image' => $getImageUrl($vj->image),
            'banner' => $getImageUrl($vj->banner),
            'rating' => (float) $vj->rating,
            'specialty' => $vj->genres->pluck('name')->toArray(),
            'bio' => $vj->bio,
            'translatedCount' => $vj->translated_count,
            'isVerified' => (bool) ($vj->is_verified ?? false),
        ]);
    }
}
