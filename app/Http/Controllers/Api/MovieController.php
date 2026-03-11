<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Movie;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;

class MovieController extends Controller
{
    public function index(Request $request)
    {
        $query = Movie::where('is_active', true)
            ->where('media_type', 'MOVIE')
            ->with(['genres', 'vj', 'category', 'actors']);

        // Filters
        if ($request->has('category')) {
            $categoryParam = $request->get('category');
            $query->whereHas('category', function ($q) use ($categoryParam) {
                $q->where('slug', $categoryParam)
                  ->orWhere('name', $categoryParam);
            });
        }

        if ($request->has('genre')) {
            $genreParam = $request->get('genre');
            $query->whereHas('genres', function ($q) use ($genreParam) {
                $q->where('slug', $genreParam)
                  ->orWhere('name', $genreParam);
            });
        }

        if ($request->has('vj')) {
            $vjParam = $request->get('vj');
            // Support both slug and ID
            $query->whereHas('vj', function ($q) use ($vjParam) {
                $q->where('is_active', true)
                  ->where(function ($subQ) use ($vjParam) {
                      $subQ->where('slug', $vjParam)
                           ->orWhere('id', $vjParam);
                  });
            });
        }

        // Filter by VJ presence (English = no VJ, Translated = has VJ)
        if ($request->has('has_vj')) {
            $hasVj = $request->get('has_vj');
            if ($hasVj === 'false' || $hasVj === '0') {
                // English movies (no VJ)
                $query->whereNull('vj_id');
            } else {
                // Translated movies (has VJ)
                $query->whereNotNull('vj_id');
            }
        }

        // Commercial / access filters
        $filter = $request->get('filter');

        // Free content (e.g. ?filter=free or ?is_free=1)
        if ($filter === 'free' || $request->get('is_free') === '1' || $request->get('is_free') === 'true') {
            $query->where('is_free', true);
        }

        // Rental catalog (?filter=rent)
        if ($filter === 'rent') {
            $query->whereNotNull('price_rent')
                  ->where('price_rent', '>', 0);
        }

        // Purchase catalog (?filter=purchase)
        if ($filter === 'purchase') {
            $query->whereNotNull('price_buy')
                  ->where('price_buy', '>', 0);
        }

        // Premium subscription catalog (?filter=premium)
        if ($filter === 'premium') {
            $query->where('is_premium', true);
        }

        // Featured catalog (?filter=featured)
        if ($filter === 'featured') {
            $query->where('is_featured', true);
        }

        // Handle sorting
        $sort = $request->get('sort', 'trending');
        $order = $request->get('order', 'desc');
        
        if ($sort === 'latest') {
            $query->orderBy('created_at', $order);
        } elseif ($sort === 'trending') {
            // Trending based on total views (views_count + manual_views)
            $query->orderByRaw('(views_count + manual_views) DESC');
        } elseif ($sort === 'rating') {
            $query->orderBy('rating', $order);
        } else {
            // Default: trending by views
            $query->orderByRaw('(views_count + manual_views) DESC');
        }
        
        // Secondary sort by created_at for consistency
        $query->orderBy('created_at', 'desc');
        
        $movies = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'data' => $movies->map(fn($m) => $this->formatMovie($m)),
            'meta' => [
                'current_page' => $movies->currentPage(),
                'last_page' => $movies->lastPage(),
                'per_page' => $movies->perPage(),
                'total' => $movies->total(),
            ],
        ]);
    }

    public function show($id)
    {
        // Support both slug and ID (backward compatibility)
        $movie = Movie::where('is_active', true)
            ->with(['genres', 'vj', 'category', 'actors', 'seasons.episodes'])
            ->where(function ($query) use ($id) {
                $query->where('id', $id)
                      ->orWhere('slug', $id);
            })
            ->first();

        if (!$movie) {
            return response()->json(['message' => 'Movie not found'], 404);
        }

        return response()->json($this->formatMovie($movie));
    }

    public function tvShows(Request $request)
    {
        $query = Movie::where('is_active', true)
            ->where('media_type', 'SERIES')
            ->with(['genres', 'vj', 'category', 'seasons.episodes']);

        // Handle sorting (same as index method)
        $sort = $request->get('sort', 'trending');
        $order = $request->get('order', 'desc');
        
        if ($sort === 'latest') {
            $query->orderBy('created_at', $order);
        } elseif ($sort === 'trending') {
            // Trending based on total views (views_count + manual_views)
            $query->orderByRaw('(views_count + manual_views) DESC');
        } elseif ($sort === 'rating') {
            $query->orderBy('rating', $order);
        } else {
            // Default: trending by views
            $query->orderByRaw('(views_count + manual_views) DESC');
        }
        
        // Secondary sort by created_at for consistency
        $query->orderBy('created_at', 'desc');

        $series = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'data' => $series->map(fn($s) => $this->formatMovie($s)),
            'meta' => [
                'current_page' => $series->currentPage(),
                'last_page' => $series->lastPage(),
                'per_page' => $series->perPage(),
                'total' => $series->total(),
            ],
        ]);
    }

    public function search(Request $request)
    {
        $query = $request->get('q', '');
        
        if (empty($query)) {
            return response()->json(['data' => [], 'meta' => []]);
        }

        $movies = Movie::where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%");
            })
            ->with(['genres', 'vj', 'category'])
            ->limit(20)
            ->get()
            ->map(fn($m) => $this->formatMovie($m));

        return response()->json(['data' => $movies]);
    }

    /**
     * Return up to 14 movies selected once per day.
     */
    public function selectedToday(Request $request)
    {
        $limit = (int) $request->get('limit', 14);
        if ($limit <= 0) {
            $limit = 14;
        }

        // Cache key per day
        $cacheKey = 'movies_selected_today_' . now()->toDateString();

        $secondsUntilMidnight = now()->endOfDay()->diffInSeconds(now());

        $movies = Cache::remember($cacheKey, $secondsUntilMidnight, function () use ($limit) {
            $baseQuery = Movie::where('is_active', true)
                ->where('media_type', 'MOVIE');

            // Prefer non-premium if possible
            $pool = $baseQuery->inRandomOrder()->limit(200)->get();

            if ($pool->isEmpty()) {
                return collect();
            }

            return $pool->shuffle()->take($limit);
        });

        return response()->json([
            'data' => $movies->map(fn ($m) => $this->formatMovie($m))->values(),
        ]);
    }

    private function formatMovie(Movie $movie): array
    {
        // Helper to get full URL for images
        $getImageUrl = function ($path) {
            if (empty($path)) return null;
            if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                return $path;
            }
            return asset('storage/' . $path);
        };

        return [
            'id' => $movie->id,
            'slug' => $movie->slug,
            'title' => $movie->title,
            'description' => $movie->description,
            'thumbnail' => $getImageUrl($movie->thumbnail),
            'backdrop' => $getImageUrl($movie->backdrop),
            'rating' => (float) $movie->rating,
            'releaseDate' => $movie->release_date->format('Y-m-d'),
            'category' => $movie->category->name,
            'mediaType' => $movie->media_type,
            'vj' => $movie->vj ? $movie->vj->name : null,
            'genre' => $movie->genres->pluck('name')->toArray(),
            'trendingScore' => $movie->trending_score,
            'viewsCount' => (int) ($movie->views_count + $movie->manual_views),
            'is_free' => (bool) $movie->is_free,
            'is_premium' => (bool) $movie->is_premium,
            'createdAt' => $movie->created_at->toIso8601String(),
            'videoUrl' => $movie->video_url,
            'duration' => $movie->duration,
            'priceRent' => $movie->price_rent ? (int) $movie->price_rent : null,
            'priceBuy' => $movie->price_buy ? (int) $movie->price_buy : null,
            'downloadEnabled' => $movie->download_enabled,
            'tmdbId' => $movie->tmdb_id,
            'imdbId' => $movie->imdb_id,
            'tagline' => $movie->tagline,
            'trailers' => $movie->trailers()->where('is_active', true)->get()->map(function ($trailer) {
                return [
                    'id' => $trailer->id,
                    'name' => $trailer->name,
                    'key' => $trailer->key,
                    'youtubeUrl' => $trailer->youtube_url,
                    'embedUrl' => $trailer->embed_url,
                    'type' => $trailer->type,
                ];
            }),
            'crew' => [
                'directors' => $movie->crew()->where('job', 'Director')->get()->map(function ($crew) {
                    return [
                        'name' => $crew->name,
                        'profileImage' => $crew->profile_image,
                    ];
                }),
            ],
            'keywords' => $movie->keywords->pluck('name')->toArray(),
            'cast' => $movie->actors->map(function ($actor) use ($getImageUrl) {
                return [
                    'id' => $actor->id,
                    'name' => $actor->name,
                    'image' => $getImageUrl($actor->image),
                    'role' => $actor->pivot->role ?? null,
                ];
            })->toArray(),
            'seasons' => $movie->seasons->map(function ($season) {
                return [
                    'id' => $season->id,
                    'number' => $season->number,
                    'episodes' => $season->episodes->map(function ($episode) {
                        return [
                            'id' => $episode->id,
                            'number' => $episode->number,
                            'title' => $episode->title,
                            'thumbnail' => $episode->thumbnail,
                            'duration' => $episode->duration,
                            'description' => $episode->description,
                            'videoUrl' => $episode->video_url,
                        ];
                    })->toArray(),
                ];
            })->toArray(),
        ];
    }
}
