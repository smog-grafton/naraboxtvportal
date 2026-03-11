<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VJ;
use Illuminate\Http\Request;

class VJController extends Controller
{
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
            ];
        });

        return response()->json([
            'data' => $vjs,
        ]);
    }

    public function show($id)
    {
        // Support both slug and ID (backward compatibility)
        $vj = VJ::where('is_active', true)
            ->with(['genres', 'movies' => function ($query) {
                $query->where('is_active', true)
                      ->with(['genres', 'category'])
                      ->orderBy('trending_score', 'desc');
            }])
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

        // Helper to get full URL for images (for movies)
        $getMovieImageUrl = function ($path) {
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
            'movies' => $vj->movies->map(function ($movie) use ($getMovieImageUrl) {
                return [
                    'id' => $movie->id,
                    'slug' => $movie->slug,
                    'title' => $movie->title,
                    'thumbnail' => $getMovieImageUrl($movie->thumbnail),
                    'rating' => (float) $movie->rating,
                    'genre' => $movie->genres->pluck('name')->toArray(),
                ];
            }),
        ]);
    }
}
