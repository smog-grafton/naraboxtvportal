<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Actor;
use Illuminate\Http\Request;

class ActorController extends Controller
{
    public function index(Request $request)
    {
        $query = Actor::query();

        // Search filter
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('bio', 'like', "%{$search}%");
            });
        }

        // Order by number of movies (trending actors)
        if ($request->has('trending') && $request->get('trending')) {
            $query->withCount('movies')
                  ->orderBy('movies_count', 'desc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $perPage = $request->get('per_page', 20);
        $actors = $query->paginate($perPage);

        // Helper to get full URL for images
        $getImageUrl = function ($path) {
            if (empty($path)) return null;
            if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                return $path;
            }
            return asset('storage/' . $path);
        };

        return response()->json([
            'data' => $actors->map(function ($actor) use ($getImageUrl) {
                return [
                    'id' => $actor->id,
                    'name' => $actor->name,
                    'slug' => $actor->slug,
                    'image' => $getImageUrl($actor->image),
                    'bio' => $actor->bio,
                    'moviesCount' => $actor->movies()->count(),
                ];
            }),
            'meta' => [
                'current_page' => $actors->currentPage(),
                'last_page' => $actors->lastPage(),
                'per_page' => $actors->perPage(),
                'total' => $actors->total(),
            ],
        ]);
    }

    public function show($id)
    {
        // Support both slug and ID (backward compatibility)
        $actor = Actor::with(['movies' => function ($query) {
            $query->where('is_active', true)
                  ->with(['genres', 'vj', 'category'])
                  ->orderBy('trending_score', 'desc')
                  ->orderBy('created_at', 'desc');
        }])
        ->where(function ($query) use ($id) {
            $query->where('id', $id)
                  ->orWhere('slug', $id);
        })
        ->first();

        if (!$actor) {
            return response()->json(['message' => 'Actor not found'], 404);
        }

        // Helper to get full URL for images
        $getImageUrl = function ($path) {
            if (empty($path)) return null;
            if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                return $path;
            }
            return asset('storage/' . $path);
        };

        // Helper to format movie
        $formatMovie = function ($movie) use ($getImageUrl) {
            return [
                'id' => $movie->id,
                'title' => $movie->title,
                'description' => $movie->description,
                'thumbnail' => $getImageUrl($movie->thumbnail),
                'backdrop' => $getImageUrl($movie->backdrop),
                'rating' => (float) $movie->rating,
                'releaseDate' => $movie->release_date->format('Y-m-d'),
                'category' => $movie->category->name ?? '',
                'mediaType' => $movie->media_type,
                'vj' => $movie->vj ? $movie->vj->name : null,
                'genre' => $movie->genres->pluck('name')->toArray(),
                'trendingScore' => $movie->trending_score,
                'accessType' => $movie->access_type,
                'videoUrl' => $movie->video_url,
                'duration' => $movie->duration,
                'role' => $movie->pivot->role ?? null,
            ];
        };

        return response()->json([
            'id' => $actor->id,
            'name' => $actor->name,
            'slug' => $actor->slug,
            'image' => $getImageUrl($actor->image),
            'bio' => $actor->bio,
            'moviesCount' => $actor->movies()->count(),
            'movies' => $actor->movies->map($formatMovie),
        ]);
    }

    public function trending(Request $request)
    {
        $limit = $request->get('limit', 10);
        
        $actors = Actor::withCount('movies')
            ->orderBy('movies_count', 'desc')
            ->limit($limit)
            ->get();

        // Helper to get full URL for images
        $getImageUrl = function ($path) {
            if (empty($path)) return null;
            if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                return $path;
            }
            return asset('storage/' . $path);
        };

        return response()->json([
            'data' => $actors->map(function ($actor) use ($getImageUrl) {
                return [
                    'id' => $actor->id,
                    'name' => $actor->name,
                    'slug' => $actor->slug,
                    'image' => $getImageUrl($actor->image),
                    'bio' => $actor->bio,
                    'moviesCount' => $actor->movies_count,
                ];
            }),
        ]);
    }
}

