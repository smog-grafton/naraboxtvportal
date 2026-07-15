<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Episode;
use App\Models\Movie;
use App\Models\TVShow;
use App\Models\VideoSource;
use App\Services\SimilarTitlesService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;

/**
 * @group Movies
 *
 * List and fetch movies. Supports filters: category, genre, vj, has_vj, filter (free|rent|purchase|premium|featured), sort (trending|latest|rating).
 */
class MovieController extends Controller
{
    private function complianceNoticePayload(): array
    {
        return [
            'type' => 'copyright',
            'title' => 'Content unavailable',
            'message' => 'This title has been restricted following a copyright or compliance request received by NaraboxTV. We take intellectual property and platform safety seriously and review all reports in accordance with our compliance process.',
            'actions' => [
                ['type' => 'link', 'label' => 'View Copyright Policy', 'href' => '/dmca'],
                ['type' => 'link', 'label' => 'Contact Support', 'href' => '/contact'],
            ],
        ];
    }

    /**
     * List movies
     *
     * Query params: category, genre, vj, has_vj, filter, sort, order, per_page.
     */
    public function index(Request $request)
    {
        $query = Movie::where('is_active', true)
            ->where('media_type', 'MOVIE')
            ->publiclyVisible()
            ->with(['genres', 'vj', 'mediaLibrary', 'category', 'videoSources']);

        // Filters
        if ($request->has('category')) {
            $categoryParam = $request->get('category');
            $query->whereHas('category', function ($q) use ($categoryParam) {
                $q->where('slug', $categoryParam)
                  ->orWhere('name', $categoryParam);
            });
        }

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

        // Prefer direct FK when provided (matches TV shows API and mobile clients)
        if ($request->has('vj_id')) {
            $query->where('vj_id', $request->vj_id);
        } elseif ($request->has('vj')) {
            $vjParam = $request->get('vj');
            $query->whereHas('vj', function ($q) use ($vjParam) {
                $q->where('is_active', true)
                  ->where(function ($subQ) use ($vjParam) {
                      $subQ->where('slug', $vjParam)
                           ->orWhere('id', $vjParam)
                           ->orWhere('name', $vjParam);
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
            'data' => $movies->map(fn($m) => $this->formatMovieSummary($m)),
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
            ->with(['genres', 'vj.genres', 'mediaLibrary', 'category', 'actors', 'videoSources', 'seasons.episodes.videoSources'])
            ->where(function ($query) use ($id) {
                $query->where('id', $id)
                      ->orWhere('slug', $id);
            })
            ->first();

        if (!$movie) {
            return response()->json(['message' => 'Movie not found'], 404);
        }

        $status = $movie->content_status ?? 'published';
        $isRestricted = $status === 'dmca_removed';

        $payload = $this->formatMovie($movie);
        if ($isRestricted) {
            $payload['compliance_notice'] = $this->complianceNoticePayload();
        }

        return response()->json($payload);
    }

    /**
     * Similar titles for a movie, scored by shared genre/VJ/language/category/country/cast,
     * with a trending-only fallback when there aren't enough relevant matches.
     */
    public function similar(Request $request, $id, SimilarTitlesService $similarTitlesService)
    {
        $movie = Movie::where('is_active', true)
            ->where(function ($query) use ($id) {
                $query->where('id', $id)
                      ->orWhere('slug', $id);
            })
            ->first();

        if (!$movie) {
            return response()->json(['message' => 'Movie not found'], 404);
        }

        $limit = (int) $request->get('limit', 10);
        $results = $similarTitlesService->forMovie($movie, $limit);

        return response()->json([
            'data' => $results->map(function ($item) {
                return $item instanceof TVShow
                    ? app(TVShowController::class)->formatTVShowSummary($item)
                    : $this->formatMovieSummary($item);
            })->values(),
        ]);
    }

    public function tvShows(Request $request)
    {
        $query = Movie::where('is_active', true)
            ->where('media_type', 'SERIES')
            ->publiclyVisible()
            ->with(['genres', 'vj', 'mediaLibrary', 'category', 'videoSources']);

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
            'data' => $series->map(fn($s) => $this->formatMovieSummary($s)),
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

        /** @var \Illuminate\Support\Collection<int, \App\Models\Movie> $movies */
        $movies = Movie::where('is_active', true)
            ->publiclyVisible()
            ->where(function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%");
            })
            ->with(['genres', 'vj', 'mediaLibrary', 'category', 'videoSources'])
            ->limit(20)
            ->get()
            ->map(fn (Movie $m) => $this->formatMovieSummary($m));

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

        /** @var \Illuminate\Support\Collection<int, \App\Models\Movie> $movies */
        $movies = Cache::remember($cacheKey, $secondsUntilMidnight, function () use ($limit) {
            $baseQuery = Movie::where('is_active', true)
                ->where('media_type', 'MOVIE')
                ->publiclyVisible()
                ->with(['genres', 'vj', 'mediaLibrary', 'category', 'videoSources']);

            // Prefer non-premium if possible
            $pool = $baseQuery->inRandomOrder()->limit(200)->get();

            if ($pool->isEmpty()) {
                /** @var \Illuminate\Support\Collection<int, \App\Models\Movie> $empty */
                $empty = collect();
                return $empty;
            }

            return $pool->shuffle()->take($limit);
        });

        return response()->json([
            'data' => $movies->map(fn (Movie $m) => $this->formatMovieSummary($m))->values(),
        ]);
    }

    public function formatMovieSummary(Movie $movie): array
    {
        $getImageUrl = function ($path) {
            if (empty($path)) return null;
            if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                return $path;
            }
            return asset('storage/' . $path);
        };

        $status = $movie->content_status ?? 'published';
        $isRestricted = $status === 'dmca_removed';

        $releaseDate = null;
        if ($movie->release_date instanceof \Carbon\CarbonInterface) {
            $releaseDate = $movie->release_date->format('Y-m-d');
        } elseif ($movie->release_date) {
            $releaseDate = (string) $movie->release_date;
        }

        $videoUrl = $isRestricted ? null : $this->resolvePlayableVideoUrl($movie);

        $payload = [
            'id' => $movie->id,
            'slug' => $movie->slug,
            'title' => $movie->title,
            'description' => $movie->description,
            'thumbnail' => $getImageUrl($movie->thumbnail),
            'backdrop' => $getImageUrl($movie->backdrop),
            'rating' => (float) $movie->rating,
            'releaseDate' => $releaseDate,
            'category' => $movie->category?->name,
            'mediaType' => $movie->media_type,
            'media_type' => $movie->media_type === 'SERIES' ? 'TV_SHOW' : $movie->media_type,
            'vj' => $movie->vj ? $movie->vj->name : null,
            'creator' => $this->formatCreator($movie),
            'genre' => $movie->genres->pluck('name')->toArray(),
            'trendingScore' => $movie->trending_score,
            'viewsCount' => (int) ($movie->views_count + $movie->manual_views),
            'accessType' => $movie->access_type,
            'is_free' => (bool) $movie->is_free,
            'is_premium' => (bool) $movie->is_premium,
            'createdAt' => $movie->created_at?->toIso8601String(),
            'videoUrl' => $videoUrl,
            'duration' => $movie->duration,
            'priceRent' => $movie->price_rent ? (int) $movie->price_rent : null,
            'priceBuy' => $movie->price_buy ? (int) $movie->price_buy : null,
            'downloadEnabled' => $isRestricted ? false : $movie->download_enabled,
            'status' => $status,
            'is_playable' => ! $isRestricted && $videoUrl !== null,
            'is_downloadable' => ! $isRestricted && (bool) ($movie->download_enabled ?? false),
        ];

        if ($isRestricted) {
            $payload['compliance_notice'] = $this->complianceNoticePayload();
        }

        return $payload;
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

        $status = $movie->content_status ?? 'published';
        $isRestricted = $status === 'dmca_removed';

        $releaseDate = null;
        if ($movie->release_date instanceof \Carbon\CarbonInterface) {
            $releaseDate = $movie->release_date->format('Y-m-d');
        } elseif ($movie->release_date) {
            $releaseDate = (string) $movie->release_date;
        }

        $videoUrl = $isRestricted ? null : $this->resolvePlayableVideoUrl($movie);

        $payload = [
            'id' => $movie->id,
            'slug' => $movie->slug,
            'title' => $movie->title,
            'description' => $movie->description,
            'thumbnail' => $getImageUrl($movie->thumbnail),
            'backdrop' => $getImageUrl($movie->backdrop),
            'rating' => (float) $movie->rating,
            'releaseDate' => $releaseDate,
            'category' => $movie->category->name,
            'mediaType' => $movie->media_type,
            'vj' => $movie->vj ? $movie->vj->name : null,
            'creator' => $this->formatCreator($movie),
            'genre' => $movie->genres->pluck('name')->toArray(),
            'trendingScore' => $movie->trending_score,
            'viewsCount' => (int) ($movie->views_count + $movie->manual_views),
            'is_free' => (bool) $movie->is_free,
            'is_premium' => (bool) $movie->is_premium,
            'createdAt' => $movie->created_at->toIso8601String(),
            // Do not expose direct URLs for restricted content (player endpoint is also blocked).
            'videoUrl' => $videoUrl,
            'duration' => $movie->duration,
            'priceRent' => $movie->price_rent ? (int) $movie->price_rent : null,
            'priceBuy' => $movie->price_buy ? (int) $movie->price_buy : null,
            'downloadEnabled' => $isRestricted ? false : $movie->download_enabled,
            'status' => $status,
            'is_playable' => ! $isRestricted && $videoUrl !== null,
            'is_downloadable' => ! $isRestricted && (bool) ($movie->download_enabled ?? false),
            'tmdbId' => $movie->tmdb_id,
            'imdbId' => $movie->imdb_id,
            'tagline' => $movie->tagline,
            'trailers' => $isRestricted
                ? []
                : $movie->trailers()->where('is_active', true)->get()->map(function ($trailer) {
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
                            'videoUrl' => $this->resolvePlayableVideoUrl($episode),
                        ];
                    })->toArray(),
                ];
            })            ->toArray(),
        ];

        if ($isRestricted) {
            $payload['compliance_notice'] = $this->complianceNoticePayload();
        }

        return $payload;
    }

    private function resolvePlayableVideoUrl(Movie|Episode $sourceable): ?string
    {
        $legacyUrl = trim((string) ($sourceable->video_url ?? ''));
        if ($legacyUrl !== '') {
            return $legacyUrl;
        }

        $sources = $sourceable->relationLoaded('videoSources')
            ? $sourceable->videoSources
            : $sourceable->videoSources()->get();

        $preferredSource = $sources
            ->filter(fn (VideoSource $source) => (bool) $source->is_active && $this->usableSourceUrl($source) !== null)
            ->sortByDesc(fn (VideoSource $source) => (int) $source->is_primary)
            ->first();

        if (! $preferredSource) {
            $preferredSource = $sources
                ->filter(fn (VideoSource $source) => $this->usableSourceUrl($source) !== null)
                ->sortByDesc(fn (VideoSource $source) => (int) $source->is_primary)
                ->first();
        }

        return $preferredSource ? $this->usableSourceUrl($preferredSource) : null;
    }

    private function usableSourceUrl(VideoSource $source): ?string
    {
        $metadata = is_array($source->metadata) ? $source->metadata : [];

        foreach ([
            $metadata['mp4_play_url'] ?? null,
            $metadata['mp4_url'] ?? null,
            $metadata['download_mp4_url'] ?? null,
            $metadata['download_url'] ?? null,
            $metadata['original_url'] ?? null,
            $metadata['public_url'] ?? null,
            $source->full_url ?? null,
            $metadata['hls_master_url'] ?? null,
            $metadata['hls_url'] ?? null,
        ] as $candidate) {
            $url = trim((string) $candidate);

            if ($url !== '') {
                return $url;
            }
        }

        return null;
    }

    private function formatCreator(Movie $movie): ?array
    {
        $getImageUrl = function ($path) {
            if (empty($path)) return null;
            if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                return $path;
            }
            return asset('storage/' . $path);
        };
        if ($movie->mediaLibrary) {
            return [
                'id' => $movie->mediaLibrary->id,
                'type' => 'media_library',
                'name' => $movie->mediaLibrary->name,
                'slug' => $movie->mediaLibrary->slug,
                'isVerified' => (bool) $movie->mediaLibrary->is_verified,
                'image' => $getImageUrl($movie->mediaLibrary->image),
                'bio' => $movie->mediaLibrary->bio,
                'specialty' => null,
                'translatedCount' => null,
            ];
        }
        if ($movie->vj) {
            return [
                'id' => $movie->vj->id,
                'type' => 'vj',
                'name' => $movie->vj->name,
                'slug' => $movie->vj->slug,
                'isVerified' => (bool) $movie->vj->is_verified,
                'image' => $getImageUrl($movie->vj->image),
                'specialty' => $movie->vj->relationLoaded('genres')
                    ? $movie->vj->genres->pluck('name')->values()->all()
                    : null,
                'bio' => $movie->vj->bio,
                'translatedCount' => $movie->vj->translated_count ? (int) $movie->vj->translated_count : null,
            ];
        }
        return null;
    }
}
