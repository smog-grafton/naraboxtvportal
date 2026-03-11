<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TVShow;
use Illuminate\Http\Request;

class TVShowController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = TVShow::query()
                ->where('is_active', true)
                ->with('genres', 'vj', 'category', 'actors', 'seasons.episodes');

            // Category filter - support both ID and slug/name
            if ($request->has('category_id')) {
                $query->where('category_id', $request->category_id);
            } elseif ($request->has('category')) {
                $categoryParam = $request->get('category');
                $query->whereHas('category', function ($q) use ($categoryParam) {
                    $q->where('slug', $categoryParam)
                      ->orWhere('name', $categoryParam);
                });
            }

            // Genre filter - support both ID and slug/name
            if ($request->has('genre_id')) {
                $query->whereHas('genres', function ($q) use ($request) {
                    $q->where('genres.id', $request->genre_id);
                });
            } elseif ($request->has('genre')) {
                $genreParam = $request->get('genre');
                $query->whereHas('genres', function ($q) use ($genreParam) {
                    $q->where('slug', $genreParam)
                      ->orWhere('name', $genreParam);
                });
            }

            // VJ filter - support both ID and slug
            if ($request->has('vj_id')) {
                $query->where('vj_id', $request->vj_id);
            } elseif ($request->has('vj')) {
                $vjParam = $request->get('vj');
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
                    $query->whereNull('vj_id');
                } else {
                    $query->whereNotNull('vj_id');
                }
            }

            // Commercial / access filters (mirror MovieController)
            $filter = $request->get('filter');

            // Free shows
            if ($filter === 'free' || $request->get('is_free') === '1' || $request->get('is_free') === 'true') {
                $query->where('is_free', true);
            }

            // Rental catalog
            if ($filter === 'rent') {
                $query->whereNotNull('price_rent')
                      ->where('price_rent', '>', 0);
            }

            // Purchase catalog
            if ($filter === 'purchase') {
                $query->whereNotNull('price_buy')
                      ->where('price_buy', '>', 0);
            }

            // Premium catalog
            if ($filter === 'premium') {
                $query->where('is_premium', true);
            }

            // Featured catalog
            if ($filter === 'featured') {
                $query->where('is_featured', true);
            }

            // Sorting (align with MovieController)
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
                $query->orderByRaw('(views_count + manual_views) DESC');
            }

            // Secondary sort by created_at for consistency
            $query->orderBy('created_at', 'desc');

            $tvShows = $query->paginate($request->get('per_page', 20));

            return response()->json([
                'data' => $tvShows->map(fn($tv) => $this->formatTVShow($tv)),
                'meta' => [
                    'current_page' => $tvShows->currentPage(),
                    'last_page' => $tvShows->lastPage(),
                    'per_page' => $tvShows->perPage(),
                    'total' => $tvShows->total(),
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('TVShowController::index error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to load TV shows',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(string $id)
    {
        // Support both slug and ID (backward compatibility)
        $tvShow = TVShow::with('genres', 'vj', 'category', 'actors', 'seasons.episodes')
                       ->where('is_active', true)
                       ->where(function ($query) use ($id) {
                           $query->where('id', $id)
                                 ->orWhere('slug', $id);
                       })
                       ->first();

        if (!$tvShow) {
            return response()->json(['message' => 'TV Show not found'], 404);
        }

        return response()->json($this->formatTVShow($tvShow));
    }

    private function formatTVShow(TVShow $tvShow): array
    {
        $getImageUrl = function ($path) {
            if (empty($path)) return null;
            if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                return $path;
            }
            return asset('storage/' . $path);
        };

        return [
            'id' => $tvShow->id,
            'slug' => $tvShow->slug,
            'title' => $tvShow->title,
            'description' => $tvShow->description,
            'thumbnail' => $getImageUrl($tvShow->thumbnail),
            'backdrop' => $getImageUrl($tvShow->backdrop),
            'rating' => (float) $tvShow->rating,
            'releaseDate' => $tvShow->release_date->format('Y-m-d'),
            'category' => $tvShow->category->name,
            'mediaType' => 'SERIES',
            'vj' => $tvShow->vj ? $tvShow->vj->name : null,
            'genre' => $tvShow->genres->pluck('name')->toArray(),
            'trendingScore' => $tvShow->trending_score,
            'accessType' => $tvShow->access_type,
            'is_free' => (bool) $tvShow->is_free,
            'is_premium' => (bool) $tvShow->is_premium,
            'duration' => $tvShow->duration,
            'priceRent' => $tvShow->price_rent ? (int) $tvShow->price_rent : null,
            'priceBuy' => $tvShow->price_buy ? (int) $tvShow->price_buy : null,
            'downloadEnabled' => $tvShow->download_enabled,
            'seasons' => $tvShow->seasons->map(function ($season) use ($getImageUrl) {
                return [
                    'id' => $season->id,
                    'number' => $season->number,
                    'title' => $season->title,
                    'description' => $season->description,
                    'episodes' => $season->episodes->map(function ($episode) use ($getImageUrl) {
                        return [
                            'id' => $episode->id,
                            'number' => $episode->number,
                            'title' => $episode->title,
                            'thumbnail' => $getImageUrl($episode->thumbnail),
                            'duration' => $episode->duration,
                            'description' => $episode->description,
                            'videoUrl' => $episode->video_url,
                        ];
                    }),
                ];
            }),
            'cast' => $tvShow->actors->map(function ($actor) use ($getImageUrl) {
                return [
                    'id' => $actor->id,
                    'name' => $actor->name,
                    'image' => $getImageUrl($actor->image),
                    'role' => $actor->pivot->role ?? 'Actor',
                ];
            })->toArray(),
            'crew' => [
                'directors' => [], // Can be added if directors relationship exists
            ],
            'keywords' => [], // Can be added if keywords exist
            'trailers' => [], // Can be added if trailers exist
        ];
    }
}
