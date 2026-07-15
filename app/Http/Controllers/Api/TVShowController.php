<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Episode;
use App\Models\Movie;
use App\Models\TVShow;
use App\Models\VideoSource;
use App\Services\SimilarTitlesService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * @group TV Shows
 *
 * List and fetch TV shows with seasons and episodes.
 */
class TVShowController extends Controller
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

    public function index(Request $request)
    {
        try {
            $query = TVShow::query()
                ->where('is_active', true)
                ->publiclyVisible()
                ->with('genres', 'vj', 'mediaLibrary', 'category');

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
                               ->orWhere('id', $vjParam)
                               ->orWhere('name', $vjParam);
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
                'data' => $tvShows->map(fn($tv) => $this->formatTVShowSummary($tv)),
                'meta' => [
                    'current_page' => $tvShows->currentPage(),
                    'last_page' => $tvShows->lastPage(),
                    'per_page' => $tvShows->perPage(),
                    'total' => $tvShows->total(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('TVShowController::index error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to load TV shows',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(string $id)
    {
        // Support both slug and ID (backward compatibility)
        $tvShow = TVShow::with('genres', 'vj.genres', 'mediaLibrary', 'category', 'actors', 'seasons.episodes.videoSources')
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

    /**
     * Similar titles for a TV show, scored by shared genre/VJ/language/category/country/cast,
     * with a trending-only fallback when there aren't enough relevant matches.
     */
    public function similar(Request $request, $id, SimilarTitlesService $similarTitlesService)
    {
        $tvShow = TVShow::where('is_active', true)
            ->where(function ($query) use ($id) {
                $query->where('id', $id)
                      ->orWhere('slug', $id);
            })
            ->first();

        if (!$tvShow) {
            return response()->json(['message' => 'TV Show not found'], 404);
        }

        $limit = (int) $request->get('limit', 10);
        $results = $similarTitlesService->forTvShow($tvShow, $limit);

        return response()->json([
            'data' => $results->map(function ($item) {
                return $item instanceof Movie
                    ? app(MovieController::class)->formatMovieSummary($item)
                    : $this->formatTVShowSummary($item);
            })->values(),
        ]);
    }

    public function formatTVShowSummary(TVShow $tvShow): array
    {
        $getImageUrl = function ($path) {
            if (empty($path)) return null;
            if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                return $path;
            }
            return asset('storage/' . $path);
        };

        $status = $tvShow->content_status ?? 'published';
        $isRestricted = $status === 'dmca_removed';

        $releaseDate = null;
        if ($tvShow->release_date instanceof \Carbon\CarbonInterface) {
            $releaseDate = $tvShow->release_date->format('Y-m-d');
        } elseif ($tvShow->release_date) {
            $releaseDate = (string) $tvShow->release_date;
        }

        $payload = [
            'id' => $tvShow->id,
            'slug' => $tvShow->slug,
            'title' => $tvShow->title,
            'description' => $tvShow->description,
            'thumbnail' => $getImageUrl($tvShow->thumbnail),
            'backdrop' => $getImageUrl($tvShow->backdrop),
            'rating' => (float) $tvShow->rating,
            'releaseDate' => $releaseDate,
            'category' => $tvShow->category?->name,
            'mediaType' => 'SERIES',
            'media_type' => 'TV_SHOW',
            'vj' => $tvShow->vj ? $tvShow->vj->name : null,
            'creator' => $this->formatCreator($tvShow),
            'genre' => $tvShow->genres->pluck('name')->toArray(),
            'trendingScore' => $tvShow->trending_score,
            'accessType' => $tvShow->access_type,
            'is_free' => (bool) $tvShow->is_free,
            'is_premium' => (bool) $tvShow->is_premium,
            'createdAt' => $tvShow->created_at?->toIso8601String(),
            'videoUrl' => null,
            'duration' => $tvShow->duration,
            'priceRent' => $tvShow->price_rent ? (int) $tvShow->price_rent : null,
            'priceBuy' => $tvShow->price_buy ? (int) $tvShow->price_buy : null,
            'downloadEnabled' => $isRestricted ? false : $tvShow->download_enabled,
            'status' => $status,
            'is_playable' => ! $isRestricted,
            'is_downloadable' => ! $isRestricted && (bool) ($tvShow->download_enabled ?? false),
        ];

        if ($isRestricted) {
            $payload['compliance_notice'] = $this->complianceNoticePayload();
        }

        return $payload;
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

        $status = $tvShow->content_status ?? 'published';
        $isRestricted = $status === 'dmca_removed';

        $releaseDate = null;
        if ($tvShow->release_date instanceof \Carbon\CarbonInterface) {
            $releaseDate = $tvShow->release_date->format('Y-m-d');
        } elseif ($tvShow->release_date) {
            $releaseDate = (string) $tvShow->release_date;
        }

        $payload = [
            'id' => $tvShow->id,
            'slug' => $tvShow->slug,
            'title' => $tvShow->title,
            'description' => $tvShow->description,
            'thumbnail' => $getImageUrl($tvShow->thumbnail),
            'backdrop' => $getImageUrl($tvShow->backdrop),
            'rating' => (float) $tvShow->rating,
            'releaseDate' => $releaseDate,
            'category' => $tvShow->category->name,
            'mediaType' => 'SERIES',
            'media_type' => 'TV_SHOW',
            'vj' => $tvShow->vj ? $tvShow->vj->name : null,
            'creator' => $this->formatCreator($tvShow),
            'genre' => $tvShow->genres->pluck('name')->toArray(),
            'trendingScore' => $tvShow->trending_score,
            'accessType' => $tvShow->access_type,
            'is_free' => (bool) $tvShow->is_free,
            'is_premium' => (bool) $tvShow->is_premium,
            'duration' => $tvShow->duration,
            'priceRent' => $tvShow->price_rent ? (int) $tvShow->price_rent : null,
            'priceBuy' => $tvShow->price_buy ? (int) $tvShow->price_buy : null,
            'downloadEnabled' => $isRestricted ? false : $tvShow->download_enabled,
            'status' => $status,
            'is_playable' => ! $isRestricted,
            'is_downloadable' => ! $isRestricted && (bool) ($tvShow->download_enabled ?? false),
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
                            'videoUrl' => $this->resolvePlayableVideoUrl($episode),
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

        if ($isRestricted) {
            $payload['compliance_notice'] = $this->complianceNoticePayload();
        }

        return $payload;
    }

    private function resolvePlayableVideoUrl(Episode $episode): ?string
    {
        $legacyUrl = trim((string) ($episode->video_url ?? ''));
        if ($legacyUrl !== '') {
            return $legacyUrl;
        }

        $sources = $episode->relationLoaded('videoSources')
            ? $episode->videoSources
            : $episode->videoSources()->get();

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

    private function formatCreator(TVShow $tvShow): ?array
    {
        $getImageUrl = function ($path) {
            if (empty($path)) return null;
            if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                return $path;
            }
            return asset('storage/' . $path);
        };
        if ($tvShow->mediaLibrary) {
            return [
                'id' => $tvShow->mediaLibrary->id,
                'type' => 'media_library',
                'name' => $tvShow->mediaLibrary->name,
                'slug' => $tvShow->mediaLibrary->slug,
                'isVerified' => (bool) $tvShow->mediaLibrary->is_verified,
                'image' => $getImageUrl($tvShow->mediaLibrary->image),
                'bio' => $tvShow->mediaLibrary->bio,
                'specialty' => null,
                'translatedCount' => null,
            ];
        }
        if ($tvShow->vj) {
            return [
                'id' => $tvShow->vj->id,
                'type' => 'vj',
                'name' => $tvShow->vj->name,
                'slug' => $tvShow->vj->slug,
                'isVerified' => (bool) $tvShow->vj->is_verified,
                'image' => $getImageUrl($tvShow->vj->image),
                'specialty' => $tvShow->vj->relationLoaded('genres')
                    ? $tvShow->vj->genres->pluck('name')->values()->all()
                    : null,
                'bio' => $tvShow->vj->bio,
                'translatedCount' => $tvShow->vj->translated_count ? (int) $tvShow->vj->translated_count : null,
            ];
        }
        return null;
    }
}
