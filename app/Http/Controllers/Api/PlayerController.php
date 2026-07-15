<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Movie;
use App\Models\TVShow;
use App\Models\Episode;
use App\Models\PlaybackSession;
use App\Models\PlayerPreference;
use App\Models\WatchHistory;
use App\Services\BunnyStreamClientService;
use App\Services\CdnMediaClientService;
use App\Services\CdnPlaybackReadinessService;
use App\Services\PendingPaymentResolverService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * @group Player & Downloads
 *
 * Playback manifest (video URLs, HLS, subtitles, download sources). Optional auth for premium/rent/buy.
 */
class PlayerController extends Controller
{
    public function __construct(
        private readonly PendingPaymentResolverService $pendingPaymentResolver,
    ) {
    }

    private function restrictedPayload(): array
    {
        return [
            'code' => 'CONTENT_RESTRICTED',
            'status' => 'dmca_removed',
            'title' => 'Content unavailable',
            'message' => 'This title has been restricted following a copyright or compliance request received by NaraboxTV. We take intellectual property and platform safety seriously and review all reports in accordance with our compliance process.',
            'actions' => ['copyright_policy', 'contact_support'],
        ];
    }

    /**
     * Get playback manifest for a movie or TV show episode
     *
     * Resolve the best video source, subtitles, and downloads for a movie or TV episode.
     * This powers the watch page player.
     *
     * Pass `media_type=MOVIE` for movies (default) or `media_type=TV_SHOW` for series.
     * For TV shows, also pass the `episode` query param with the episode ID.
     *
     * Access rules:
     * - Free content (`is_free = 1`) is always playable (no auth required).
     * - Premium content (`is_premium = 1`) requires an active subscription.
     * - Paid content (`price_rent`/`price_buy`) requires a successful rent or purchase.
     *
     * On success, returns:
     * - `movie`: minimal media metadata (id, title, images, download_enabled)
     * - `episode`: minimal episode metadata when applicable
     * - `videoUrl`: primary play URL (HLS master or MP4)
     * - `videoSources`: all available qualities and formats
     * - `subtitles`: all available subtitle tracks
     * - `duration`: duration in seconds (best-effort)
     * - `poster`: poster/backdrop URL for the player
     * - `downloadSources`: downloadable variants (if enabled and user has access)
     * - `playback`: normalized playback manifest (`type`, `url`, `hls_master_url`, `mp4_play_url`, `qualities`, etc.)
     *
     * @urlParam id string required The movie or TV show identifier (numeric `id` or `slug`). Example: 1
     * @queryParam media_type string MOVIE or TV_SHOW. Defaults to MOVIE. Example: MOVIE
     * @queryParam episode integer The `id` of the episode when `media_type` is TV_SHOW. Example: 419
     *
     * @response 200 {
     *  "movie": {
     *    "id": 1,
     *    "title": "Zootopia 2",
     *    "thumbnail": "https://portal.naraboxtv.com/storage/tmdb/posters/tmdb_f158459be819affd8c9f257f75a49f15.jpg",
     *    "backdrop": "https://portal.naraboxtv.com/storage/tmdb/backdrops/tmdb_7ee2bf6a30418364a91d7d4cffb592d0.jpg",
     *    "download_enabled": true
     *  },
     *  "episode": null,
     *  "videoUrl": "https://cdn.narabox.example/hls/master.m3u8",
     *  "videoSources": [
     *    {
     *      "id": 101,
     *      "url": "https://cdn.narabox.example/hls/master.m3u8",
     *      "quality": "auto",
     *      "format": "hls",
     *      "type": "fetched",
     *      "isPrimary": true,
     *      "duration": 6420
     *    }
     *  ],
     *  "subtitles": [
     *    {
     *      "id": 12,
     *      "src": "https://portal.naraboxtv.com/storage/subtitles/zootopia2-en.vtt",
     *      "label": "English",
     *      "language": "en",
     *      "kind": "subtitles",
     *      "default": true,
     *      "format": "vtt"
     *    }
     *  ],
     *  "duration": 6420,
     *  "poster": "https://portal.naraboxtv.com/storage/tmdb/backdrops/tmdb_7ee2bf6a30418364a91d7d4cffb592d0.jpg",
     *  "downloadSources": [
     *    {
     *      "id": 55,
     *      "type": "fetched",
     *      "quality": "1080p",
     *      "format": "mp4",
     *      "label": "1080p MP4",
     *      "url": "https://portal.naraboxtv.com/api/v1/downloads/55",
     *      "download_url": "https://portal.naraboxtv.com/api/v1/downloads/55",
     *      "file_size": 2147483648,
     *      "source_url": null,
     *      "file_path": "downloads/movies/zootopia2-1080p.mp4"
     *    }
     *  ],
     *  "playback": {
     *    "type": "hls",
     *    "url": "https://cdn.narabox.example/hls/master.m3u8",
     *    "hls_master_url": "https://cdn.narabox.example/hls/master.m3u8",
     *    "mp4_play_url": "https://cdn.narabox.example/mp4/zootopia2-1080p.mp4",
     *    "mp4_url": "https://cdn.narabox.example/mp4/zootopia2-1080p.mp4",
     *    "download_url": "https://portal.naraboxtv.com/api/v1/downloads/55",
     *    "sources": [
     *      {
     *        "id": "cdn-auto",
     *        "url": "https://cdn.narabox.example/hls/master.m3u8",
     *        "quality": "auto",
     *        "format": "hls",
     *        "type": "fetched",
     *        "isPrimary": true,
     *        "duration": null
     *      }
     *    ],
     *    "subtitles": [
     *      {
     *        "id": 12,
     *        "src": "https://portal.naraboxtv.com/storage/subtitles/zootopia2-en.vtt",
     *        "label": "English",
     *        "language": "en",
     *        "kind": "subtitles",
     *        "default": true,
     *        "format": "vtt"
     *      }
     *    ],
     *    "qualities": [
     *      {
     *        "id": "auto",
     *        "label": "AUTO",
     *        "url": "https://cdn.narabox.example/hls/master.m3u8",
     *        "bandwidth": 4500000,
     *        "width": 1920,
     *        "height": 1080
     *      }
     *    ]
     *  }
     * }
     *
     * @response 403 {
     *  "error": "This content requires a premium subscription. Subscribe to access premium content.",
     *  "message": "This content requires a premium subscription. Subscribe to access premium content.",
     *  "has_access": false,
     *  "reason": "This content requires a premium subscription. Subscribe to access premium content.",
     *  "requires_payment": false,
     *  "requires_subscription": true,
     *  "requires_auth": true,
     *  "access_type": "PREMIUM",
     *  "can_rent": true,
     *  "can_buy": true,
     *  "rent_price": 1000,
     *  "buy_price": 2200,
     *  "is_free": false,
     *  "is_premium": true,
     *  "pending_payment": false,
     *  "transaction_ref": null
     * }
     *
     * @response 404 {
     *  "error": "Media not found"
     * }
     */
    public function show($id, Request $request, CdnMediaClientService $cdnMediaClientService)
    {
        $episodeId = $request->get('episode');
        $mediaType = $request->get('media_type', 'MOVIE'); // Get media_type from request, default to MOVIE
        
        // Support both slug and ID (backward compatibility)
        $movie = null;
        $tvShow = null;
        $isTVShow = false;
        
        // Use media_type to determine which model to query first
        if ($mediaType === 'TV_SHOW') {
            // Try TVShow first if media_type is TV_SHOW
            $tvShow = TVShow::where('is_active', true)
                ->with(['genres', 'vj', 'category'])
                ->where(function ($query) use ($id) {
                    $query->where('id', $id)
                          ->orWhere('slug', $id);
                })
                ->first();
            
            if ($tvShow) {
                if (($tvShow->content_status ?? 'published') === 'dmca_removed') {
                    return response()->json($this->restrictedPayload(), 403);
                }
                $isTVShow = true;
                // Create a virtual movie object for compatibility
                $movie = (object) [
                    'id' => $tvShow->id,
                    'title' => $tvShow->title,
                    'thumbnail' => $tvShow->thumbnail,
                    'backdrop' => $tvShow->backdrop,
                    'is_free' => $tvShow->is_free,
                    'is_premium' => $tvShow->is_premium,
                    'price_rent' => $tvShow->price_rent,
                    'price_buy' => $tvShow->price_buy,
                    'download_enabled' => $tvShow->download_enabled,
                    'duration' => $tvShow->duration,
                    'video_url' => null, // TV shows don't have direct video_url
                ];
            }
        } else {
            // Try Movie first if media_type is MOVIE or not specified
            $movie = Movie::where('is_active', true)
                ->with(['genres', 'vj', 'category'])
                ->where(function ($query) use ($id) {
                    $query->where('id', $id)
                          ->orWhere('slug', $id);
                })
                ->first();

            if ($movie && (($movie->content_status ?? 'published') === 'dmca_removed')) {
                return response()->json($this->restrictedPayload(), 403);
            }
        }
        
        // If not found, try the other type (fallback for backward compatibility)
        if (!$movie && !$tvShow) {
            if ($mediaType === 'TV_SHOW') {
                // Try Movie as fallback
                $movie = Movie::where('is_active', true)
                    ->with(['genres', 'vj', 'category'])
                    ->where(function ($query) use ($id) {
                        $query->where('id', $id)
                              ->orWhere('slug', $id);
                    })
                    ->first();

                if ($movie && (($movie->content_status ?? 'published') === 'dmca_removed')) {
                    return response()->json($this->restrictedPayload(), 403);
                }
            } else {
                // Try TVShow as fallback
                $tvShow = TVShow::where('is_active', true)
                    ->with(['genres', 'vj', 'category'])
                    ->where(function ($query) use ($id) {
                        $query->where('id', $id)
                              ->orWhere('slug', $id);
                    })
                    ->first();
                
                if ($tvShow) {
                    if (($tvShow->content_status ?? 'published') === 'dmca_removed') {
                        return response()->json($this->restrictedPayload(), 403);
                    }
                    $isTVShow = true;
                    // Create a virtual movie object for compatibility
                    $movie = (object) [
                        'id' => $tvShow->id,
                        'title' => $tvShow->title,
                        'thumbnail' => $tvShow->thumbnail,
                        'backdrop' => $tvShow->backdrop,
                        'is_free' => $tvShow->is_free,
                        'is_premium' => $tvShow->is_premium,
                        'price_rent' => $tvShow->price_rent,
                        'price_buy' => $tvShow->price_buy,
                        'download_enabled' => $tvShow->download_enabled,
                        'duration' => $tvShow->duration,
                        'video_url' => null,
                    ];
                }
            }
        }
        
        // If still not found, return error
        if (!$movie && !$tvShow) {
            return response()->json(['error' => 'Media not found'], 404);
        }

        // Resolve user from Bearer token (route has no auth middleware)
        $user = Auth::guard('sanctum')->user();

        $episode = null;
        if ($episodeId) {
            if ($isTVShow) {
                $tvShowId = $tvShow->id;
                $episode = Episode::where('id', $episodeId)
                    ->whereHas('season', function ($q) use ($tvShowId) {
                        $q->where('tv_show_id', $tvShowId);
                    })
                    ->first();
            } else {
                $movieId = is_object($movie) ? $movie->id : $movie;
                $episode = Episode::where('id', $episodeId)
                    ->whereHas('season', function ($q) use ($movieId) {
                        $q->where('media_id', $movieId);
                    })
                    ->first();
            }
        }

        if (! $episode && $isTVShow) {
            $episode = $this->resolveTvShowEpisode($tvShow, $user);
        }
        
        // Debug: Log user authentication status
        if ($isTVShow && $tvShow->is_premium) {
            Log::info('PlayerController access check', [
                'user_id' => $user ? $user->id : null,
                'user_email' => $user ? $user->email : null,
                'tv_show_id' => $tvShow->id,
                'tv_show_slug' => $tvShow->slug,
                'is_premium' => $tvShow->is_premium,
                'is_free' => $tvShow->is_free,
                'authenticated' => $user !== null,
            ]);
        }
        
        // Check if content is free first (before access check)
        $isFree = $isTVShow ? $tvShow->is_free : $movie->is_free;
        
        $accessInfo = $this->checkAccessDetailed($movie, $user, $isTVShow ? $tvShow : null);
        
        // Debug: Log access result
        if ($isTVShow && $tvShow->is_premium && $user) {
            Log::info('PlayerController subscription check result', [
                'user_id' => $user->id,
                'tv_show_id' => $tvShow->id,
                'is_premium' => $tvShow->is_premium,
                'has_access' => $accessInfo['has_access'],
                'access_type' => $accessInfo['access_type'] ?? null,
                'reason' => $accessInfo['reason'] ?? null,
            ]);
        }

        // For free content, allow access even if user is not authenticated
        // But still check access for other content types
        if (!$isFree && !$accessInfo['has_access']) {
            return response()->json([
                'error' => $accessInfo['reason'] ?? 'Access denied',
                'message' => $accessInfo['reason'] ?? 'Access denied',
                'has_access' => false,
                'reason' => $accessInfo['reason'] ?? 'Access denied',
                'requires_payment' => !($isTVShow ? $tvShow->is_free : $movie->is_free) && !($isTVShow ? $tvShow->is_premium : $movie->is_premium),
                'requires_subscription' => $isTVShow ? $tvShow->is_premium : $movie->is_premium,
                'requires_auth' => !$user,
                'access_type' => $accessInfo['access_type'] ?? null,
                'can_rent' => !empty($isTVShow ? $tvShow->price_rent : $movie->price_rent),
                'can_buy' => !empty($isTVShow ? $tvShow->price_buy : $movie->price_buy),
                'rent_price' => $isTVShow ? $tvShow->price_rent : $movie->price_rent,
                'buy_price' => $isTVShow ? $tvShow->price_buy : $movie->price_buy,
                'is_free' => $isTVShow ? $tvShow->is_free : $movie->is_free,
                'is_premium' => $isTVShow ? $tvShow->is_premium : $movie->is_premium,
                'pending_payment' => $accessInfo['pending_payment'] ?? false,
                'transaction_ref' => $accessInfo['transaction_ref'] ?? null,
            ], 403);
        }

        // Get all active video sources for quality switching
        // For TV shows, use episode or TV show; for movies, use episode or movie
        $sourceable = $episode ?: ($isTVShow ? $tvShow : $movie);
        $databaseMarkers = $this->resolveModelPlaybackMarkers($sourceable);
        if ((bool) config('services.cdn.hls_readiness_check_on_playback', false)) {
            try {
                app(CdnPlaybackReadinessService::class)->syncForSourceable($sourceable);
            } catch (\Throwable $exception) {
                Log::warning('CDN playback readiness sync failed', [
                    'sourceable_type' => $sourceable::class,
                    'sourceable_id' => $sourceable->id ?? null,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $allVideoSourceModels = $sourceable->videoSources()
            ->orderBy('is_primary', 'desc')
            ->orderBy('quality', 'desc')
            ->get();
        $activeVideoSourceModels = $allVideoSourceModels
            ->filter(fn ($source): bool => $this->canUseVideoSourceModel($source, false))
            ->values();
        $fallbackVideoSourceModels = $allVideoSourceModels
            ->filter(fn ($source): bool => $this->canUseVideoSourceModel($source, true))
            ->values();

        $videoSourceModels = $activeVideoSourceModels->isNotEmpty()
            ? $activeVideoSourceModels
            : $fallbackVideoSourceModels;

        if ($activeVideoSourceModels->isEmpty() && $fallbackVideoSourceModels->isNotEmpty()) {
            $this->reactivateSafeFallbackSources($fallbackVideoSourceModels);
        }

        $allow720p = $this->canStream720p($user);

        $videoSources = $videoSourceModels
            ->map(function ($source) {
                return [
                    'id' => $source->id,
                    'url' => $this->usableVideoSourceUrl($source),
                    'quality' => $source->quality ?? 'auto',
                    'format' => $source->format ?? 'mp4',
                    'type' => in_array($source->type, ['contabo_object_storage', 'tele_ob'], true) ? 'url' : $source->type,
                    'isPrimary' => $source->is_primary,
                    'duration' => $source->duration_seconds, // Include duration if available
                ];
            })
            ->filter(fn (array $source): bool => is_string($source['url'] ?? null) && trim((string) $source['url']) !== '')
            ->filter(fn (array $source): bool => $this->isAllowedPlaybackQuality($source, $allow720p))
            ->sortBy(fn (array $source): int => $this->playbackSourceSortScore($source))
            ->values();
        $preferBrowserSafePlayback = $this->shouldPreferBrowserSafePlayback($request);
        
        // Helper to get full URL for images (define early so it can be used)
        $getImageUrl = function ($path) {
            if (empty($path)) return null;
            if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                return $path;
            }
            return asset('storage/' . $path);
        };

        // Get all active subtitles
        $subtitles = $sourceable->subtitles()
            ->where('is_active', true)
            ->orderBy('is_default', 'desc')
            ->orderBy('sort_order', 'asc')
            ->get()
            ->map(function ($subtitle) {
                return [
                    'id' => $subtitle->id,
                    'src' => $subtitle->full_url,
                    'label' => $subtitle->label ?? $subtitle->language,
                    'language' => $subtitle->language,
                    'kind' => 'subtitles',
                    'default' => $subtitle->is_default,
                    'format' => $subtitle->format,
                ];
            });
        
        // Get primary video source or first available
        $primarySource = $this->selectPreferredVideoSource($videoSources->values()->toArray(), $preferBrowserSafePlayback);
        if (is_array($primarySource) && isset($primarySource['url'])) {
            $selectedSourceUrl = (string) $primarySource['url'];
            $videoSources = $videoSources->map(function (array $source) use ($selectedSourceUrl) {
                $source['isPrimary'] = (($source['url'] ?? null) === $selectedSourceUrl);
                return $source;
            });
        }
        $videoUrl = $primarySource['url'] ?? null;
        
        // Fallback to video_url field if no video sources
        if (empty($videoUrl)) {
            if ($episode) {
                $videoUrl = $episode->video_url;
            } elseif ($isTVShow) {
                $videoUrl = null; // TV shows don't have direct video_url
            } else {
                $videoUrl = $movie->video_url;
            }
        }
        
        // No default video - return error if no source available
        if (empty($videoUrl)) {
            return response()->json([
                'error' => 'No video source available',
                'requiresVideoSource' => true,
            ], 404);
        }

        // Get download sources if enabled
        // For free content, always include download sources
        // For other content, only include if user has access
        $downloadSources = collect();
        $downloadEnabled = ($episode && $episode->download_enabled) || (!$episode && $movie->download_enabled);
        
        if ($downloadEnabled && ($isFree || $accessInfo['has_access'])) {
            $this->syncPlayableSourcesToDownloads($videoSourceModels);

            $apiUrl = config('app.url') . '/api/v1';
            $downloadSources = $sourceable->downloadSources()
                ->where('is_active', true)
                ->where(function ($query): void {
                    $query->whereNull('format')
                        ->orWhere('format', '!=', 'm3u8');
                })
                ->orderBy('sort_order')
                ->get()
                ->filter(function ($source): bool {
                    $url = (string) ($source->url ?: $source->file_path ?: '');
                    $path = strtolower((string) parse_url($url, PHP_URL_PATH));

                    return ! str_ends_with($path, '.m3u8');
                })
                ->map(function ($source) use ($apiUrl) {
                    // Use the download endpoint URL instead of direct file URL
                    return [
                        'id' => $source->id,
                        'type' => in_array($source->type, ['contabo_object_storage', 'tele_ob'], true) ? 'url' : $source->type,
                        'quality' => $source->quality,
                        'format' => $source->format,
                        'label' => $source->label ?: ($source->quality . ' ' . strtoupper($source->format)),
                        'url' => $apiUrl . '/downloads/' . $source->id, // Use download endpoint
                        'download_url' => $apiUrl . '/downloads/' . $source->id,
                        'file_size' => $source->file_size,
                        'source_url' => $source->url, // Include original URL for URL type sources
                        'file_path' => $source->file_path, // Include file path for local sources
                    ];
                });
        }

        // Get video duration from primary source or movie/episode duration
        $duration = $primarySource['duration'] ?? null;
        if (!$duration && $episode && $episode->duration) {
            // Parse episode duration (e.g., "45m" -> 2700 seconds)
            $duration = $this->parseDuration($episode->duration);
        } elseif (!$duration) {
            $mediaDuration = $isTVShow ? ($tvShow->duration ?? null) : ($movie->duration ?? null);
            if ($mediaDuration) {
                $duration = $this->parseDuration($mediaDuration);
            }
        }

        $playback = $this->buildPlaybackPayload(
            $videoSourceModels,
            $videoSources->values()->toArray(),
            $subtitles->values()->toArray(),
            $videoUrl,
            $downloadSources->toArray(),
            $cdnMediaClientService,
            $databaseMarkers,
            $preferBrowserSafePlayback,
            $allow720p
        );

        if (is_array($playback)) {
            if (isset($playback['url']) && is_string($playback['url']) && $playback['url'] !== '') {
                $videoUrl = $playback['url'];
            }
            if (isset($playback['sources']) && is_array($playback['sources']) && $playback['sources'] !== []) {
                $videoSources = collect($playback['sources']);
            }
        }

        Log::info('Player playback response prepared', [
            'media_id' => $isTVShow ? $tvShow->id : $movie->id,
            'episode_id' => $episode?->id,
            'playback_type' => $playback['type'] ?? 'legacy-mp4',
            'playback_url' => $playback['url'] ?? $videoUrl,
            'hls_master_url' => $playback['hls_master_url'] ?? null,
            'mp4_play_url' => $playback['mp4_play_url'] ?? ($playback['mp4_url'] ?? null),
            'download_url' => $playback['download_url'] ?? null,
        ]);

        $resumeEntry = $user
            ? WatchHistory::query()
                ->where('user_id', $user->id)
                ->where('media_id', $isTVShow ? $tvShow->id : $movie->id)
                ->where('media_type', $isTVShow ? 'TV_SHOW' : 'MOVIE')
                ->where('episode_id', $episode?->id)
                ->first()
            : null;
        $preferencePayload = $user ? $this->formatPreferencePayload($user->playerPreference) : null;
        $nextEpisode = $isTVShow && $episode && $tvShow
            ? $this->resolveNextEpisode($tvShow, $episode)
            : null;
        $playbackMarkers = $this->mergePlaybackMarkers(
            is_array($playback) ? ($playback['markers'] ?? null) : null,
            $databaseMarkers
        );

        return response()->json([
            'movie' => [
                'id' => $isTVShow ? $tvShow->id : $movie->id,
                'title' => $isTVShow ? $tvShow->title : $movie->title,
                'thumbnail' => $getImageUrl($isTVShow ? $tvShow->thumbnail : $movie->thumbnail),
                'backdrop' => $getImageUrl($isTVShow ? $tvShow->backdrop : $movie->backdrop),
                'download_enabled' => $isTVShow ? $tvShow->download_enabled : $movie->download_enabled,
            ],
            'episode' => $episode ? [
                'id' => $episode->id,
                'number' => $episode->number,
                'title' => $episode->title,
                'download_enabled' => $episode->download_enabled,
                'seasonNumber' => $episode->season?->number,
            ] : null,
            'nextEpisode' => $nextEpisode ? [
                'id' => $nextEpisode->id,
                'number' => $nextEpisode->number,
                'title' => $nextEpisode->title,
                'download_enabled' => $nextEpisode->download_enabled,
                'seasonNumber' => $nextEpisode->season?->number,
                'thumbnail' => $getImageUrl($nextEpisode->thumbnail),
            ] : null,
            'videoUrl' => $videoUrl,
            'videoSources' => $videoSources->values()->toArray(), // All available sources for quality switching
            'subtitles' => $subtitles->values()->toArray(), // All available subtitles
            'duration' => $duration, // Duration in seconds
            'poster' => $getImageUrl($isTVShow ? $tvShow->backdrop : $movie->backdrop) ?? $getImageUrl($isTVShow ? $tvShow->thumbnail : $movie->thumbnail), // Use backdrop as poster, fallback to thumbnail
            'downloadSources' => $downloadSources,
            'playback' => $playback,
            'playbackMarkers' => $playbackMarkers,
            'playbackChapters' => $playbackMarkers['chapters'] ?? [],
            'thumbnailTrack' => $playback['thumbnails'] ?? null,
            'resume' => $this->formatResumePayload($resumeEntry, $duration),
            'preferences' => $preferencePayload,
        ]);
    }

    /**
     * Update watch history for the current user
     *
     * Called from the player to persist progress for a given movie (and optional episode).
     * The most recent entry per `(user_id, media_id, episode_id)` is kept up to date.
     *
     * @authenticated
     *
     * @bodyParam media_id integer required The `id` of the movie being watched. Must exist in the `movies` table. Example: 1
     * @bodyParam episode_id integer nullable The `id` of the episode when watching a TV show episode. Example: 419
     * @bodyParam progress_seconds integer required Current playhead position in seconds. Minimum: 0. Example: 1200
     * @bodyParam total_seconds integer nullable Total duration in seconds (if known). Minimum: 0. Example: 6420
     *
     * @response 200 {
     *  "success": true
     * }
     *
     * @response 401 {
     *  "error": "Unauthorized"
     * }
     */
    public function updateHistory(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $request->validate([
            'media_id' => 'required|integer|min:1',
            'media_type' => 'required|in:MOVIE,TV_SHOW',
            'episode_id' => 'nullable|exists:episodes,id',
            'progress_seconds' => 'required|integer|min:0',
            'total_seconds' => 'nullable|integer|min:0',
        ]);

        $mediaType = (string) $request->media_type;
        $mediaExists = $mediaType === 'TV_SHOW'
            ? TVShow::whereKey($request->media_id)->exists()
            : Movie::whereKey($request->media_id)->exists();

        if (! $mediaExists) {
            return response()->json(['error' => 'Media not found'], 422);
        }

        if ($request->episode_id) {
            $episodeBelongsToMedia = Episode::where('id', $request->episode_id)
                ->whereHas('season', function ($query) use ($mediaType, $request) {
                    if ($mediaType === 'TV_SHOW') {
                        $query->where('tv_show_id', $request->media_id);
                    } else {
                        $query->where('media_id', $request->media_id);
                    }
                })
                ->exists();

            if (! $episodeBelongsToMedia) {
                return response()->json(['error' => 'Episode does not belong to this title'], 422);
            }
        }

        WatchHistory::updateOrCreate(
            [
                'user_id' => $user->id,
                'media_id' => $request->media_id,
                'media_type' => $mediaType,
                'episode_id' => $request->episode_id,
            ],
            [
                'progress_seconds' => $request->progress_seconds,
                'total_seconds' => $request->total_seconds,
                'last_watched_at' => now(),
            ]
        );

        return response()->json(['success' => true]);
    }

    /**
     * Get watch history for the current user
     *
     * Returns a flat list of recent watch sessions ordered by `lastWatched` (most recent first).
     * Each item references a movie and optionally an episode.
     *
     * @authenticated
     *
     * @response 200 {
     *  "data": [
     *    {
     *      "id": 10,
     *      "mediaId": 1,
     *      "episodeId": null,
     *      "progressSeconds": 1200,
     *      "totalSeconds": 6420,
     *      "lastWatched": "2026-03-10T20:15:30+03:00"
     *    }
     *  ]
     * }
     *
     * @response 401 {
     *  "error": "Unauthorized"
     * }
     */
    public function getHistory(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $history = WatchHistory::where('user_id', $user->id)
            ->orderBy('last_watched_at', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'mediaId' => $item->media_id,
                    'mediaType' => $item->media_type,
                    'episodeId' => $item->episode_id,
                    'progressSeconds' => $item->progress_seconds,
                    'totalSeconds' => $item->total_seconds,
                    'lastWatched' => $item->last_watched_at->toIso8601String(),
                ];
            });

        return response()->json(['data' => $history]);
    }

    public function getPreferences(Request $request)
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return response()->json([
            'data' => $this->formatPreferencePayload($user->playerPreference),
        ]);
    }

    public function updatePreferences(Request $request)
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'autoplay_next_episode' => 'sometimes|boolean',
            'preferred_subtitle' => 'nullable|string|max:32',
            'preferred_subtitle_enabled' => 'sometimes|boolean',
            'preferred_quality' => 'nullable|string|max:32',
            'volume' => 'sometimes|numeric|min:0|max:1',
            'muted' => 'sometimes|boolean',
            'theater_mode' => 'sometimes|boolean',
            'keyboard_shortcuts_enabled' => 'sometimes|boolean',
        ]);

        $preference = PlayerPreference::updateOrCreate(
            ['user_id' => $user->id],
            $validated
        );

        return response()->json([
            'data' => $this->formatPreferencePayload($preference),
        ]);
    }

    public function syncSession(Request $request)
    {
        $user = Auth::guard('sanctum')->user();

        $validated = $request->validate([
            'session_uuid' => 'required|string|max:64',
            'media_id' => 'required|integer|min:1',
            'media_type' => 'required|in:MOVIE,TV_SHOW',
            'episode_id' => 'nullable|integer|exists:episodes,id',
            'lifecycle' => 'nullable|in:start,heartbeat,end',
            'current_time' => 'nullable|numeric|min:0',
            'startup_ms' => 'nullable|integer|min:0',
            'buffer_delta_ms' => 'nullable|integer|min:0',
            'quality' => 'nullable|string|max:32',
            'error_message' => 'nullable|string|max:500',
            'exit_reason' => 'nullable|string|max:32',
            'preferences' => 'nullable|array',
            'device_type' => 'nullable|string|max:32',
        ]);

        $session = PlaybackSession::firstOrNew([
            'session_uuid' => $validated['session_uuid'],
        ]);

        $lifecycle = (string) ($validated['lifecycle'] ?? 'heartbeat');
        $currentTime = (float) ($validated['current_time'] ?? 0);
        $bufferDeltaMs = (int) ($validated['buffer_delta_ms'] ?? 0);
        $quality = isset($validated['quality']) ? trim((string) $validated['quality']) : null;
        $errorMessage = isset($validated['error_message']) ? trim((string) $validated['error_message']) : null;

        if (! $session->exists) {
            $session->fill([
                'user_id' => $user?->id,
                'media_id' => $validated['media_id'],
                'media_type' => $validated['media_type'],
                'episode_id' => $validated['episode_id'] ?? null,
                'device_type' => $validated['device_type'] ?? $this->guessDeviceType($request->userAgent()),
                'preferences' => $validated['preferences'] ?? null,
                'started_at' => now(),
                'last_ping_at' => now(),
            ]);
        } else {
            if (! $session->user_id && $user) {
                $session->user_id = $user->id;
            }
            $session->last_ping_at = now();
        }

        if (! $session->started_at) {
            $session->started_at = now();
        }

        if (! $session->device_type) {
            $session->device_type = $validated['device_type'] ?? $this->guessDeviceType($request->userAgent());
        }

        if (array_key_exists('preferences', $validated)) {
            $session->preferences = $validated['preferences'];
        }

        if (! $session->startup_ms && isset($validated['startup_ms'])) {
            $session->startup_ms = (int) $validated['startup_ms'];
        }

        $session->media_id = $validated['media_id'];
        $session->media_type = $validated['media_type'];
        $session->episode_id = $validated['episode_id'] ?? $session->episode_id;
        $session->max_position_seconds = max((float) ($session->max_position_seconds ?? 0), $currentTime);
        $session->total_watch_seconds = max((int) ($session->total_watch_seconds ?? 0), (int) floor($currentTime));

        if ($bufferDeltaMs > 0) {
            $session->buffer_count = (int) ($session->buffer_count ?? 0) + 1;
            $session->total_buffer_ms = (int) ($session->total_buffer_ms ?? 0) + $bufferDeltaMs;
        }

        if ($quality) {
            $history = is_array($session->quality_history) ? $session->quality_history : [];
            $lastQuality = end($history) ?: null;

            if ($lastQuality !== $quality) {
                $history[] = $quality;
                $session->quality_switch_count = (int) ($session->quality_switch_count ?? 0) + ($lastQuality ? 1 : 0);
                $session->quality_history = array_slice(array_values(array_unique($history)), -12);
            }

            $session->last_quality = $quality;
        }

        if ($errorMessage) {
            $errors = is_array($session->error_log) ? $session->error_log : [];
            $errors[] = [
                'message' => $errorMessage,
                'logged_at' => now()->toIso8601String(),
            ];
            $session->error_log = array_slice($errors, -15);
            $session->error_count = (int) ($session->error_count ?? 0) + 1;
        }

        if ($lifecycle === 'end') {
            $session->ended_at = now();
            $session->exit_reason = $validated['exit_reason'] ?? 'ended';
        }

        $session->save();

        return response()->json([
            'data' => [
                'session_uuid' => $session->session_uuid,
                'started_at' => $session->started_at?->toIso8601String(),
                'ended_at' => $session->ended_at?->toIso8601String(),
            ],
        ]);
    }

    private function resolveTvShowEpisode(TVShow $tvShow, $user): ?Episode
    {
        if ($user) {
            $lastWatchedEpisodeId = WatchHistory::query()
                ->where('user_id', $user->id)
                ->where('media_id', $tvShow->id)
                ->where('media_type', 'TV_SHOW')
                ->whereNotNull('episode_id')
                ->orderBy('last_watched_at', 'desc')
                ->value('episode_id');

            if ($lastWatchedEpisodeId) {
                $lastWatchedEpisode = Episode::query()
                    ->select('episodes.*')
                    ->join('seasons', 'seasons.id', '=', 'episodes.season_id')
                    ->where('seasons.tv_show_id', $tvShow->id)
                    ->where('episodes.id', $lastWatchedEpisodeId)
                    ->first();

                if ($lastWatchedEpisode) {
                    return $lastWatchedEpisode;
                }
            }
        }

        $firstPlayableEpisode = Episode::query()
            ->select('episodes.*')
            ->join('seasons', 'seasons.id', '=', 'episodes.season_id')
            ->where('seasons.tv_show_id', $tvShow->id)
            ->where(function ($query) {
                $query->whereNotNull('episodes.video_url')
                    ->orWhereHas('videoSources', function ($sourceQuery) {
                        $sourceQuery->where('is_active', true);
                    });
            })
            ->orderBy('seasons.number')
            ->orderBy('episodes.number')
            ->first();

        if ($firstPlayableEpisode) {
            return $firstPlayableEpisode;
        }

        return Episode::query()
            ->select('episodes.*')
            ->join('seasons', 'seasons.id', '=', 'episodes.season_id')
            ->where('seasons.tv_show_id', $tvShow->id)
            ->orderBy('seasons.number')
            ->orderBy('episodes.number')
            ->first();
    }

    private function resolveNextEpisode(TVShow $tvShow, Episode $currentEpisode): ?Episode
    {
        $currentEpisode->loadMissing('season');
        $seasonNumber = $currentEpisode->season?->number;

        if ($seasonNumber === null) {
            return null;
        }

        return Episode::query()
            ->with('season')
            ->select('episodes.*')
            ->join('seasons', 'seasons.id', '=', 'episodes.season_id')
            ->where('seasons.tv_show_id', $tvShow->id)
            ->where(function ($query) use ($seasonNumber, $currentEpisode) {
                $query->where('seasons.number', '>', $seasonNumber)
                    ->orWhere(function ($sameSeasonQuery) use ($seasonNumber, $currentEpisode) {
                        $sameSeasonQuery->where('seasons.number', '=', $seasonNumber)
                            ->where('episodes.number', '>', $currentEpisode->number);
                    });
            })
            ->where(function ($query) {
                $query->whereNotNull('episodes.video_url')
                    ->orWhereHas('videoSources', function ($sourceQuery) {
                        $sourceQuery->where('is_active', true);
                    });
            })
            ->orderBy('seasons.number')
            ->orderBy('episodes.number')
            ->first();
    }

    private function checkAccessDetailed($movie, $user, ?TVShow $tvShow = null): array
    {
        // Check if content is free
        $isFree = $tvShow ? $tvShow->is_free : $movie->is_free;
        
        $isPremium = $tvShow ? $tvShow->is_premium : $movie->is_premium;
        $priceRent = $tvShow ? $tvShow->price_rent : $movie->price_rent;
        $priceBuy = $tvShow ? $tvShow->price_buy : $movie->price_buy;
        
        // Check if content is free
        if ($isFree) {
            return [
                'has_access' => true,
                'access_type' => 'FREE',
                'reason' => 'Content is free',
            ];
        }

        // If no user, check if content requires authentication
        if (!$user) {
            return [
                'has_access' => false,
                'access_type' => null,
                'reason' => 'Please log in to access this content',
                'requires_auth' => true,
            ];
        }

        // PRIORITY 1: Purchased access should still unlock premium titles after subscriptions expire.
        $purchaseType = $tvShow ? TVShow::class : Movie::class;
        $purchase = \App\Models\UserPurchase::where('user_id', $user->id)
            ->where('purchasable_type', $purchaseType)
            ->where('purchasable_id', $movie->id)
            ->first();

        if ($purchase) {
            return [
                'has_access' => true,
                'access_type' => 'PURCHASED',
                'reason' => 'You own this content',
            ];
        }

        // PRIORITY 2: Active rentals should also outlive subscription expiry.
        $rentalType = $tvShow ? TVShow::class : Movie::class;
        $rental = \App\Models\UserRental::where('user_id', $user->id)
            ->where('rentable_type', $rentalType)
            ->where('rentable_id', $movie->id)
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->first();

        if ($rental) {
            return [
                'has_access' => true,
                'access_type' => 'RENTED',
                'reason' => 'You have rented this content',
                'expires_at' => $rental->expires_at,
            ];
        }

        // PRIORITY 3: Pending rent/buy access for this title comes before subscription prompts.
        $transactionType = $tvShow ? TVShow::class : Movie::class;
        $pendingTransaction = $this->pendingPaymentResolver->getPendingContentTransaction(
            $user->id,
            $transactionType,
            $movie->id,
        );

        if ($pendingTransaction) {
            $isManualReview = $pendingTransaction->paymentGateway?->type === 'MANUAL';
            return [
                'has_access' => false,
                'access_type' => 'PENDING',
                'reason' => $isManualReview
                    ? 'Your payment is pending admin approval. Access will be granted once approved.'
                    : 'We are still confirming your payment. Access unlocks automatically once the gateway responds.',
                'pending_payment' => true,
                'transaction_ref' => $pendingTransaction->transaction_ref,
            ];
        }

        // PRIORITY 4: Active subscriptions unlock premium titles when no purchase/rental exists.
        if ($isPremium) {
            $allSubscriptions = \App\Models\UserSubscription::where('user_id', $user->id)->get();

            $activeSubscription = \App\Models\UserSubscription::where('user_id', $user->id)
                ->where('status', 'ACTIVE')
                ->where('expires_at', '>', now())
                ->first();

            Log::info('Subscription check for premium content', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'is_premium' => $isPremium,
                'total_subscriptions' => $allSubscriptions->count(),
                'subscriptions' => $allSubscriptions->map(fn($s) => [
                    'id' => $s->id,
                    'status' => $s->status,
                    'expires_at' => $s->expires_at,
                    'is_expired' => $s->expires_at <= now(),
                ])->toArray(),
                'active_subscription_found' => $activeSubscription !== null,
                'active_subscription_id' => $activeSubscription ? $activeSubscription->id : null,
                'now' => now()->toDateTimeString(),
            ]);

            if ($activeSubscription) {
                return [
                    'has_access' => true,
                    'access_type' => 'SUBSCRIPTION',
                    'reason' => 'You have an active subscription',
                ];
            }

            $pendingSubscription = $this->pendingPaymentResolver->getPendingSubscriptionTransaction($user->id);

            if ($pendingSubscription) {
                $isManualReview = $pendingSubscription->paymentGateway?->type === 'MANUAL';

                return [
                    'has_access' => false,
                    'access_type' => 'PENDING',
                    'reason' => $isManualReview
                        ? 'Your subscription payment is pending admin approval. Access will be granted once approved.'
                        : 'We are still confirming your subscription payment. Access unlocks automatically once the gateway responds.',
                    'pending_payment' => true,
                    'transaction_ref' => $pendingSubscription->transaction_ref,
                    'requires_subscription' => true,
                ];
            }

            return [
                'has_access' => false,
                'access_type' => 'PREMIUM',
                'reason' => 'This content requires a premium subscription. Subscribe to access premium content.',
                'requires_subscription' => true,
                'can_rent' => !empty($priceRent),
                'can_buy' => !empty($priceBuy),
                'rent_price' => $priceRent,
                'buy_price' => $priceBuy,
            ];
        }

        // Content is not free, not premium, check if rent/buy available
        if (!empty($priceRent) || !empty($priceBuy)) {
            $options = [];
            if (!empty($priceRent)) $options[] = 'rent';
            if (!empty($priceBuy)) $options[] = 'buy';
            
            $reason = 'This content requires payment. ';
            if (count($options) === 2) {
                $reason .= 'You can rent it for UGX ' . number_format($priceRent, 0) . ' or buy it for lifetime access.';
            } elseif (!empty($priceRent)) {
                $reason .= 'You can rent it for UGX ' . number_format($priceRent, 0) . ' (30 days access).';
            } else {
                $reason .= 'You can buy it for lifetime access.';
            }
            
            return [
                'has_access' => false,
                'access_type' => 'PAID',
                'reason' => $reason,
                'requires_payment' => true,
            ];
        }

        return [
            'has_access' => false,
            'access_type' => 'UNKNOWN',
            'reason' => 'Access denied. Please contact support.',
        ];
    }

    /**
     * Parse duration string to seconds
     * Supports formats like "2h 13m", "45m", "1h", "90m"
     */
    private function parseDuration(?string $duration): ?int
    {
        if (empty($duration)) {
            return null;
        }

        $seconds = 0;
        
        // Match hours
        if (preg_match('/(\d+)\s*h/i', $duration, $matches)) {
            $seconds += (int)$matches[1] * 3600;
        }
        
        // Match minutes
        if (preg_match('/(\d+)\s*m/i', $duration, $matches)) {
            $seconds += (int)$matches[1] * 60;
        }
        
        // Match seconds
        if (preg_match('/(\d+)\s*s/i', $duration, $matches)) {
            $seconds += (int)$matches[1];
        }
        
        return $seconds > 0 ? $seconds : null;
    }


    private function canUseVideoSourceModel($source, bool $allowInactiveFallback): bool
    {
        $url = $this->usableVideoSourceUrl($source);
        if (! is_string($url) || trim($url) === '') {
            return false;
        }

        if ((bool) $source->is_active) {
            return true;
        }

        if (! $allowInactiveFallback) {
            return false;
        }

        $metadata = is_array($source->metadata ?? null) ? (array) $source->metadata : [];
        if ($this->isHlsVideoSourceModel($source)) {
            return (bool) ($metadata['cdn_ready'] ?? $metadata['cdn_hls_ready'] ?? false);
        }

        return true;
    }

    private function isHlsVideoSourceModel($source): bool
    {
        $format = strtolower((string) ($source->format ?? ''));
        if (in_array($format, ['m3u8', 'hls'], true)) {
            return true;
        }

        $url = (string) ($this->usableVideoSourceUrl($source) ?? '');
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));

        return str_ends_with($path, '.m3u8') || str_ends_with($path, '.ts');
    }

    private function usableVideoSourceUrl($source): ?string
    {
        if (! $source) {
            return null;
        }

        $metadata = is_array($source->metadata ?? null) ? (array) $source->metadata : [];

        foreach ([
            $metadata['mp4_play_url'] ?? null,
            $metadata['mp4_url'] ?? null,
            $metadata['download_mp4_url'] ?? null,
            $metadata['download_url'] ?? null,
            $metadata['original_url'] ?? null,
            $metadata['public_url'] ?? null,
            $source->full_url ?? null,
            $source->url ?? null,
            $source->file_path ?? null,
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

    private function reactivateSafeFallbackSources(\Illuminate\Support\Collection $sources): void
    {
        foreach ($sources as $source) {
            if ((bool) $source->is_active || $this->isHlsVideoSourceModel($source)) {
                continue;
            }

            \App\Models\VideoSource::withoutEvents(function () use ($source): void {
                $metadata = array_merge((array) ($source->metadata ?? []), [
                    'auto_reactivated_at' => now()->toDateTimeString(),
                    'auto_reactivated_reason' => 'playback_fallback_valid_url',
                ]);

                $source->forceFill([
                    'is_active' => true,
                    'metadata' => $metadata,
                ])->save();
            });

            Log::warning('Reactivated inactive video source with usable fallback URL during playback', [
                'video_source_id' => $source->id,
                'sourceable_type' => $source->sourceable_type,
                'sourceable_id' => $source->sourceable_id,
                'type' => $source->type,
                'format' => $source->format,
            ]);
        }
    }

    private function syncPlayableSourcesToDownloads(\Illuminate\Support\Collection $sources): void
    {
        foreach ($sources as $source) {
            if (! method_exists($source, 'syncToDownloadSource') || $this->isHlsVideoSourceModel($source)) {
                continue;
            }

            try {
                $source->syncToDownloadSource();
            } catch (\Throwable $exception) {
                Log::warning('Failed to sync playable video source to download source during playback', [
                    'video_source_id' => $source->id ?? null,
                    'sourceable_type' => $source->sourceable_type ?? null,
                    'sourceable_id' => $source->sourceable_id ?? null,
                    'type' => $source->type ?? null,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }

    private function buildPlaybackPayload(
        \Illuminate\Support\Collection $videoSourceModels,
        array $legacyVideoSources,
        array $subtitles,
        ?string $videoUrl,
        array $downloadSources,
        CdnMediaClientService $cdnMediaClientService,
        ?array $databaseMarkers = null,
        bool $preferBrowserSafePlayback = false,
        bool $allow720p = false
    ): ?array {
        if ($preferBrowserSafePlayback) {
            $legacyVideoSources = $this->orderVideoSourcesForBrowser($legacyVideoSources);
            if ($videoUrl === null || $videoUrl === '' || ! $this->isBrowserCompatibleVideoSource(['url' => $videoUrl])) {
                $preferredSource = $this->selectPreferredVideoSource($legacyVideoSources, true);
                if (is_array($preferredSource) && isset($preferredSource['url'])) {
                    $videoUrl = (string) $preferredSource['url'];
                }
            }
        }

        $defaultMarkers = $this->mergePlaybackMarkers(
            $databaseMarkers,
            $this->resolvePlaybackMarkers($videoSourceModels)
        );

        $defaultPlayback = [
            'type' => 'mp4',
            'url' => $videoUrl,
            'hls_master_url' => null,
            'mp4_play_url' => $videoUrl,
            'mp4_url' => $videoUrl,
            'download_url' => $downloadSources[0]['download_url'] ?? null,
            'sources' => $legacyVideoSources,
            'subtitles' => $subtitles,
            'qualities' => [],
            'default_quality' => '480p',
            'markers' => $defaultMarkers,
            'chapters' => $defaultMarkers['chapters'] ?? [],
            'thumbnails' => $this->resolveThumbnailTrack($videoSourceModels),
        ];

        if (! (bool) config('services.cdn.use_playback_manifest', true)) {
            return $defaultPlayback;
        }

        $bunnySource = $videoSourceModels->first(function ($source) {
            $metadata = (array) ($source->metadata ?? []);

            return $source->type === 'bunny_stream'
                || ($metadata['provider'] ?? null) === 'bunny_stream'
                || ! empty($metadata['bunny_stream_video_id']);
        });

        if ($bunnySource) {
            $bunnyPlayback = $this->buildBunnyStreamPlaybackPayload($bunnySource, $defaultPlayback, $legacyVideoSources);
            if ($bunnyPlayback !== null) {
                return $bunnyPlayback;
            }
        }

        $fetchedSource = $videoSourceModels->first(function ($source) {
            $metadata = is_array($source->metadata ?? null) ? (array) $source->metadata : [];

            return in_array($source->type, ['fetched', 'local', 'url', 'bunny_stream', 'contabo_object_storage', 'tele_ob', 'nbx-engine'], true)
                && is_array($source->metadata)
                && (($metadata['provider'] ?? null) === 'nbx_engine' || ! empty($metadata['cdn_asset_id']));
        });

        if (! $fetchedSource || ! is_array($fetchedSource->metadata ?? null)) {
            return $defaultPlayback;
        }

        $sourceMetadata = (array) ($fetchedSource->metadata ?? []);
        if (($sourceMetadata['provider'] ?? null) === 'nbx_engine') {
            $metaType = (string) ($sourceMetadata['playback_type'] ?? 'mp4');
            $metaHls = isset($sourceMetadata['hls_master_url']) ? (string) $sourceMetadata['hls_master_url'] : null;
            $metaMp4 = isset($sourceMetadata['mp4_play_url'])
                ? (string) $sourceMetadata['mp4_play_url']
                : (isset($sourceMetadata['mp4_url']) ? (string) $sourceMetadata['mp4_url'] : $videoUrl);
            $metaDownload = $this->mp4OnlyUrl($sourceMetadata['download_url'] ?? ($downloadSources[0]['download_url'] ?? null));
            $rawQualities = is_array($sourceMetadata['qualities'] ?? null) ? $sourceMetadata['qualities'] : [];
            $qualities = $this->filterPlaybackQualities($rawQualities, $allow720p);
            $lockedQualities = $this->lockedPlaybackQualities($rawQualities, $allow720p);
            $metaUrl = $metaType === 'hls' && is_string($metaHls) && $metaHls !== '' ? $metaHls : $metaMp4;

            $sources = $legacyVideoSources;
            foreach ($qualities as $quality) {
                if (! is_array($quality) || empty($quality['url'])) {
                    continue;
                }
                $sources[] = [
                    'id' => 'nbx-' . (string) ($quality['id'] ?? 'quality'),
                    'url' => (string) $quality['url'],
                    'quality' => strtolower((string) ($quality['id'] ?? $quality['label'] ?? 'auto')),
                    'format' => 'hls',
                    'type' => 'nbx-engine',
                    'isPrimary' => false,
                    'duration' => null,
                ];
            }

            return array_merge($defaultPlayback, [
                'type' => $metaType === 'hls' ? 'hls' : 'mp4',
                'url' => $metaUrl,
                'hls_master_url' => $metaHls,
                'mp4_play_url' => $metaMp4,
                'mp4_url' => $metaMp4,
                'download_url' => $metaDownload,
                'sources' => array_values(array_filter($sources, fn (array $source): bool => $this->isAllowedPlaybackQuality($source, $allow720p))),
                'qualities' => $qualities,
                'locked_qualities' => $lockedQualities,
                'default_quality' => '480p',
            ]);
        }

        $cdnAssetId = (string) ($fetchedSource->metadata['cdn_asset_id'] ?? '');
        if ($cdnAssetId === '') {
            $metaType = (string) ($sourceMetadata['playback_type'] ?? 'mp4');
            $metaHls = isset($sourceMetadata['hls_master_url']) ? (string) $sourceMetadata['hls_master_url'] : null;
            $metaMp4 = isset($sourceMetadata['mp4_play_url'])
                ? (string) $sourceMetadata['mp4_play_url']
                : (isset($sourceMetadata['mp4_url']) ? (string) $sourceMetadata['mp4_url'] : $videoUrl);
            $metaDownload = isset($sourceMetadata['download_url']) ? (string) $sourceMetadata['download_url'] : ($downloadSources[0]['download_url'] ?? null);
            $metaUrl = $metaType === 'hls' && is_string($metaHls) && $metaHls !== '' ? $metaHls : $metaMp4;

            $markers = $this->mergePlaybackMarkers(
                $this->normalizePlaybackMarkers(
                    $sourceMetadata['playback_markers']
                        ?? $sourceMetadata['markers']
                        ?? null
                ),
                $defaultPlayback['markers']
            );

            return array_merge($defaultPlayback, [
                'type' => $metaType === 'hls' ? 'hls' : 'mp4',
                'url' => $metaUrl,
                'hls_master_url' => $metaHls,
                'mp4_play_url' => $metaMp4,
                'mp4_url' => $metaMp4,
                'download_url' => $metaDownload,
                'qualities' => $this->filterPlaybackQualities(is_array($sourceMetadata['qualities'] ?? null) ? $sourceMetadata['qualities'] : [], $allow720p),
                'locked_qualities' => $this->lockedPlaybackQualities(is_array($sourceMetadata['qualities'] ?? null) ? $sourceMetadata['qualities'] : [], $allow720p),
                'default_quality' => '480p',
                'markers' => $markers,
                'chapters' => $markers['chapters'] ?? [],
                'thumbnails' => $this->normalizeThumbnailTrack(
                    $sourceMetadata['thumbnail_track']
                        ?? $sourceMetadata['thumbnail_vtt']
                        ?? $sourceMetadata['thumbnails']
                        ?? null
                ) ?? $defaultPlayback['thumbnails'],
            ]);
        }

        if (! (bool) config('services.cdn.remote_playback_manifest_lookup', false)) {
            return $defaultPlayback;
        }

        try {
            $manifestResponse = $cdnMediaClientService->getPlaybackManifest($cdnAssetId);
        } catch (\Throwable) {
            return $defaultPlayback;
        }

        $playback = is_array($manifestResponse['data']['playback'] ?? null)
            ? $manifestResponse['data']['playback']
            : null;
        if (! is_array($playback)) {
            return $defaultPlayback;
        }

        $type = (string) ($playback['type'] ?? 'mp4');
        $hlsMaster = isset($playback['hls_master_url']) ? (string) $playback['hls_master_url'] : null;
        $mp4PlayUrl = isset($playback['mp4_play_url'])
            ? (string) $playback['mp4_play_url']
            : (isset($playback['mp4_url']) ? (string) $playback['mp4_url'] : $videoUrl);
        $playbackUrl = $type === 'hls' && is_string($hlsMaster) && $hlsMaster !== ''
            ? $hlsMaster
            : $mp4PlayUrl;

        $mappedSources = [];
        $qualities = [];
        if (is_array($playback['qualities'] ?? null)) {
            foreach ($playback['qualities'] as $quality) {
                if (! is_array($quality)) {
                    continue;
                }

                $qualityUrl = isset($quality['url']) && is_string($quality['url']) ? $quality['url'] : null;
                if (! $qualityUrl) {
                    continue;
                }

                $qualityId = (string) ($quality['id'] ?? 'auto');
                $qualityLabel = (string) ($quality['label'] ?? strtoupper($qualityId));

                $qualities[] = [
                    'id' => $qualityId,
                    'label' => $qualityLabel,
                    'url' => $qualityUrl,
                    'bandwidth' => $quality['bandwidth'] ?? null,
                    'width' => $quality['width'] ?? null,
                    'height' => $quality['height'] ?? null,
                ];

                $mappedSources[] = [
                    'id' => 'cdn-' . $qualityId,
                    'url' => $qualityUrl,
                    'quality' => strtolower($qualityId),
                    'format' => $type === 'hls' ? 'hls' : 'mp4',
                    'type' => 'fetched',
                    'isPrimary' => $qualityId === 'auto',
                    'duration' => null,
                ];
            }
        }

        if ($mappedSources === [] && is_string($playbackUrl) && $playbackUrl !== '') {
            $mappedSources[] = [
                'id' => 'cdn-primary',
                'url' => $playbackUrl,
                'quality' => 'auto',
                'format' => $type === 'hls' ? 'hls' : 'mp4',
                'type' => 'fetched',
                'isPrimary' => true,
                'duration' => null,
            ];
        }

        // If CDN playback manifest only yielded a single source but we have multiple legacy video
        // sources configured in Portal (e.g. Original / 720p / HLS), prefer those so the player
        // can offer a proper quality menu instead of a single AUTO entry.
        $finalSources = $mappedSources;
        if (count($finalSources) <= 1 && count($legacyVideoSources) > 1) {
            $finalSources = $legacyVideoSources;
        }

        // Ensure primary source is first so the player respects portal primary selection
        usort($finalSources, function ($a, $b) {
            $aPrimary = ! empty($a['isPrimary']);
            $bPrimary = ! empty($b['isPrimary']);
            if ($aPrimary === $bPrimary) {
                return 0;
            }
            return $aPrimary ? -1 : 1;
        });

        $markers = $this->mergePlaybackMarkers(
            $this->normalizePlaybackMarkers(
                $playback['markers']
                    ?? $playback['playback_markers']
                    ?? $sourceMetadata['playback_markers']
                    ?? $sourceMetadata['markers']
                    ?? null
            ),
            $defaultPlayback['markers']
        );

        return [
            'type' => $type,
            'url' => $playbackUrl,
            'hls_master_url' => $hlsMaster,
            'mp4_play_url' => $mp4PlayUrl,
            'mp4_url' => $mp4PlayUrl,
            'download_url' => $playback['download_url'] ?? ($downloadSources[0]['download_url'] ?? null),
            'sources' => array_values(array_filter($finalSources !== [] ? $finalSources : $legacyVideoSources, fn (array $source): bool => $this->isAllowedPlaybackQuality($source, $allow720p))),
            'subtitles' => $subtitles,
            'qualities' => $this->filterPlaybackQualities($qualities, $allow720p),
            'locked_qualities' => $this->lockedPlaybackQualities($qualities, $allow720p),
            'default_quality' => '480p',
            'markers' => $markers,
            'chapters' => $markers['chapters'] ?? [],
            'thumbnails' => $this->normalizeThumbnailTrack(
                $playback['thumbnails']
                    ?? $playback['thumbnail_track']
                    ?? $playback['thumbnail_vtt']
                    ?? $sourceMetadata['thumbnail_track']
                    ?? $sourceMetadata['thumbnail_vtt']
                    ?? $sourceMetadata['thumbnails']
                    ?? null
            ) ?? $defaultPlayback['thumbnails'],
        ];
    }

    private function buildBunnyStreamPlaybackPayload($source, array $defaultPlayback, array $legacyVideoSources): ?array
    {
        $metadata = (array) ($source->metadata ?? []);
        $bunny = app(BunnyStreamClientService::class);
        $sourceUrl = (string) ($source->url ?: $source->file_path ?: '');
        $videoId = (string) ($metadata['bunny_stream_video_id'] ?? '');
        if ($videoId === '' && $sourceUrl !== '') {
            $videoId = (string) ($bunny->extractVideoId($sourceUrl) ?? '');
        }

        $playback = is_array($metadata['bunny_stream_playback'] ?? null)
            ? (array) $metadata['bunny_stream_playback']
            : null;

        $video = is_array($metadata['bunny_stream_video'] ?? null)
            ? (array) $metadata['bunny_stream_video']
            : null;

        if ($videoId !== ''
            && $bunny->isConfigured()
            && (bool) config('services.bunny_stream.refresh_metadata_on_playback', false)
        ) {
            try {
                $videoResponse = $bunny->getVideo($videoId);
                if (($videoResponse['ok'] ?? false) && is_array($videoResponse['data'] ?? null)) {
                    $video = (array) $videoResponse['data'];
                    $playback = $bunny->buildPlaybackPayload($videoId, $video);
                }
            } catch (\Throwable $exception) {
                Log::debug('Bunny Stream video lookup failed during playback build', [
                    'video_source_id' => $source->id ?? null,
                    'bunny_stream_video_id' => $videoId,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        if ($playback === null && $videoId !== '' && $bunny->isConfigured()) {
            $playback = $bunny->buildPlaybackPayload($videoId, $video);
        }

        $hlsMaster = isset($playback['hls_master_url']) ? (string) $playback['hls_master_url'] : null;
        $mp4PlayUrl = isset($playback['mp4_play_url'])
            ? (string) $playback['mp4_play_url']
            : (isset($playback['mp4_url']) ? (string) $playback['mp4_url'] : null);

        if (! $hlsMaster && isset($metadata['hls_master_url']) && is_string($metadata['hls_master_url'])) {
            $hlsMaster = $metadata['hls_master_url'];
        }
        if (! $mp4PlayUrl && isset($metadata['mp4_play_url']) && is_string($metadata['mp4_play_url'])) {
            $mp4PlayUrl = $metadata['mp4_play_url'];
        }

        $playbackUrl = $hlsMaster ?: $mp4PlayUrl ?: $sourceUrl;
        if ($playbackUrl === '') {
            return null;
        }

        $primarySource = [
            'id' => $source->id ?? ('bunny-' . ($videoId ?: md5($playbackUrl))),
            'url' => $playbackUrl,
            'quality' => 'auto',
            'format' => $hlsMaster ? 'hls' : 'mp4',
            'type' => 'bunny_stream',
            'isPrimary' => true,
            'duration' => $source->duration_seconds ?? null,
        ];

        $finalSources = $legacyVideoSources;
        $hasPlaybackUrl = collect($finalSources)->contains(fn ($item) => is_array($item) && ($item['url'] ?? null) === $playbackUrl);
        if (! $hasPlaybackUrl) {
            array_unshift($finalSources, $primarySource);
        }

        usort($finalSources, function ($a, $b) use ($playbackUrl) {
            $aPrimary = (($a['url'] ?? null) === $playbackUrl) || ! empty($a['isPrimary']);
            $bPrimary = (($b['url'] ?? null) === $playbackUrl) || ! empty($b['isPrimary']);
            if ($aPrimary === $bPrimary) {
                return 0;
            }

            return $aPrimary ? -1 : 1;
        });

        $markers = $this->mergePlaybackMarkers(
            $this->normalizePlaybackMarkers(
                $metadata['playback_markers']
                    ?? $metadata['markers']
                    ?? null
            ),
            $defaultPlayback['markers']
        );
        $playback = is_array($playback) ? $playback : [];

        return array_merge($defaultPlayback, [
            'provider' => 'bunny_stream',
            'type' => $hlsMaster ? 'hls' : 'mp4',
            'url' => $playbackUrl,
            'hls_master_url' => $hlsMaster,
            'mp4_play_url' => $mp4PlayUrl,
            'mp4_url' => $mp4PlayUrl,
            'download_url' => $playback['download_url'] ?? $mp4PlayUrl ?? $defaultPlayback['download_url'],
            'sources' => $finalSources,
            'qualities' => is_array($playback['qualities'] ?? null) ? $playback['qualities'] : [],
            'markers' => $markers,
            'chapters' => $markers['chapters'] ?? [],
            'thumbnails' => $defaultPlayback['thumbnails'],
            'bunny_stream' => [
                'video_id' => $videoId !== '' ? $videoId : null,
                'library_id' => $playback['library_id'] ?? $metadata['bunny_stream_library_id'] ?? null,
                'status' => $playback['status'] ?? $metadata['bunny_stream_status'] ?? null,
                'status_label' => $playback['status_label'] ?? $metadata['bunny_stream_status_label'] ?? null,
                'encode_progress' => $playback['encode_progress'] ?? $metadata['bunny_stream_encode_progress'] ?? null,
                'direct_play_url' => $playback['direct_play_url'] ?? null,
                'thumbnail_url' => $playback['thumbnail_url'] ?? null,
                'preview_url' => $playback['preview_url'] ?? null,
            ],
        ]);
    }

    private function resolvePlaybackMarkers(\Illuminate\Support\Collection $videoSourceModels): ?array
    {
        foreach ($videoSourceModels as $source) {
            if (! is_array($source->metadata ?? null)) {
                continue;
            }

            $markers = $this->normalizePlaybackMarkers(
                $source->metadata['playback_markers']
                    ?? $source->metadata['markers']
                    ?? null
            );

            if ($markers) {
                return $markers;
            }
        }

        return null;
    }

    private function resolveThumbnailTrack(\Illuminate\Support\Collection $videoSourceModels): ?string
    {
        foreach ($videoSourceModels as $source) {
            if (! is_array($source->metadata ?? null)) {
                continue;
            }

            $thumbnailTrack = $this->normalizeThumbnailTrack(
                $source->metadata['thumbnail_track']
                    ?? $source->metadata['thumbnail_vtt']
                    ?? $source->metadata['thumbnails']
                    ?? null
            );

            if ($thumbnailTrack) {
                return $thumbnailTrack;
            }
        }

        return null;
    }

    private function resolveModelPlaybackMarkers(mixed $markerable): ?array
    {
        if (! is_object($markerable) || ! method_exists($markerable, 'playbackMarkers')) {
            return null;
        }

        $markers = $markerable->playbackMarkers()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        if ($markers->isEmpty()) {
            return null;
        }

        $payload = [
            'intro' => null,
            'recap' => null,
            'credits' => null,
            'chapters' => [],
        ];

        foreach ($markers as $marker) {
            $range = [
                'start' => round((float) $marker->start_seconds, 2),
                'end' => $marker->end_seconds !== null ? round((float) $marker->end_seconds, 2) : null,
                'label' => $marker->label,
                'description' => $marker->description,
            ];

            if (in_array($marker->marker_type, ['intro', 'recap', 'credits'], true)) {
                $payload[$marker->marker_type] = $range;
                continue;
            }

            if ($marker->marker_type === 'chapter') {
                $payload['chapters'][] = $range;
            }
        }

        if ($payload['chapters'] !== []) {
            usort($payload['chapters'], function ($left, $right) {
                return ($left['start'] ?? 0) <=> ($right['start'] ?? 0);
            });
        }

        return ($payload['intro'] || $payload['recap'] || $payload['credits'] || $payload['chapters'] !== [])
            ? $payload
            : null;
    }

    private function normalizePlaybackMarkers(mixed $markers): ?array
    {
        if (! is_array($markers)) {
            return null;
        }

        $normalizeSeconds = function (mixed $value): ?float {
            if (! is_numeric($value)) {
                return null;
            }

            $seconds = (float) $value;

            return $seconds >= 0 ? round($seconds, 2) : null;
        };

        $intro = is_array($markers['intro'] ?? null)
            ? [
                'start' => $normalizeSeconds($markers['intro']['start'] ?? null),
                'end' => $normalizeSeconds($markers['intro']['end'] ?? null),
                'label' => $markers['intro']['label'] ?? null,
                'description' => $markers['intro']['description'] ?? null,
            ]
            : [
                'start' => $normalizeSeconds($markers['intro_start'] ?? $markers['intro_start_seconds'] ?? null),
                'end' => $normalizeSeconds($markers['intro_end'] ?? $markers['intro_end_seconds'] ?? null),
                'label' => $markers['intro_label'] ?? null,
                'description' => $markers['intro_description'] ?? null,
            ];

        $recap = is_array($markers['recap'] ?? null)
            ? [
                'start' => $normalizeSeconds($markers['recap']['start'] ?? null),
                'end' => $normalizeSeconds($markers['recap']['end'] ?? null),
                'label' => $markers['recap']['label'] ?? null,
                'description' => $markers['recap']['description'] ?? null,
            ]
            : [
                'start' => $normalizeSeconds($markers['recap_start'] ?? $markers['recap_start_seconds'] ?? null),
                'end' => $normalizeSeconds($markers['recap_end'] ?? $markers['recap_end_seconds'] ?? null),
                'label' => $markers['recap_label'] ?? null,
                'description' => $markers['recap_description'] ?? null,
            ];

        $credits = is_array($markers['credits'] ?? null)
            ? [
                'start' => $normalizeSeconds($markers['credits']['start'] ?? null),
                'label' => $markers['credits']['label'] ?? null,
                'description' => $markers['credits']['description'] ?? null,
            ]
            : [
                'start' => $normalizeSeconds($markers['credits_start'] ?? $markers['credits_start_seconds'] ?? null),
                'label' => $markers['credits_label'] ?? null,
                'description' => $markers['credits_description'] ?? null,
            ];

        $chapterInputs = $markers['chapters']
            ?? $markers['chapter_markers']
            ?? [];
        $chapters = [];

        if (is_array($chapterInputs)) {
            foreach ($chapterInputs as $chapter) {
                if (! is_array($chapter)) {
                    continue;
                }

                $start = $normalizeSeconds($chapter['start'] ?? $chapter['start_seconds'] ?? null);
                if ($start === null) {
                    continue;
                }

                $chapters[] = [
                    'start' => $start,
                    'end' => $normalizeSeconds($chapter['end'] ?? $chapter['end_seconds'] ?? null),
                    'label' => $chapter['label'] ?? $chapter['title'] ?? null,
                    'description' => $chapter['description'] ?? null,
                ];
            }
        }

        $payload = [
            'intro' => ($intro['start'] !== null || $intro['end'] !== null) ? $intro : null,
            'recap' => ($recap['start'] !== null || $recap['end'] !== null) ? $recap : null,
            'credits' => $credits['start'] !== null ? $credits : null,
            'chapters' => $chapters,
        ];

        return $payload['intro'] || $payload['recap'] || $payload['credits'] || $payload['chapters'] !== []
            ? $payload
            : null;
    }

    private function normalizeThumbnailTrack(mixed $thumbnailTrack): ?string
    {
        if (is_array($thumbnailTrack)) {
            $thumbnailTrack = $thumbnailTrack['src']
                ?? $thumbnailTrack['url']
                ?? $thumbnailTrack['track']
                ?? null;
        }

        if (! is_string($thumbnailTrack)) {
            return null;
        }

        $thumbnailTrack = trim($thumbnailTrack);

        return $thumbnailTrack !== '' ? $thumbnailTrack : null;
    }

    private function formatResumePayload(?WatchHistory $resumeEntry, ?int $duration): ?array
    {
        if (! $resumeEntry) {
            return null;
        }

        $totalSeconds = $resumeEntry->total_seconds ?: $duration;
        $progressSeconds = min($resumeEntry->progress_seconds, $totalSeconds ?: $resumeEntry->progress_seconds);
        $percent = $totalSeconds && $totalSeconds > 0
            ? round(($progressSeconds / $totalSeconds) * 100, 1)
            : null;

        return [
            'progressSeconds' => $progressSeconds,
            'totalSeconds' => $totalSeconds,
            'percent' => $percent,
            'lastWatched' => $resumeEntry->last_watched_at?->toIso8601String(),
        ];
    }

    private function mergePlaybackMarkers(?array $primary, ?array $fallback): ?array
    {
        if (! $primary && ! $fallback) {
            return null;
        }

        $mergeRange = function (?array $preferredRange, ?array $fallbackRange): ?array {
            if (! $preferredRange && ! $fallbackRange) {
                return null;
            }

            return array_filter([
                'start' => $preferredRange['start'] ?? $fallbackRange['start'] ?? null,
                'end' => $preferredRange['end'] ?? $fallbackRange['end'] ?? null,
                'label' => $preferredRange['label'] ?? $fallbackRange['label'] ?? null,
                'description' => $preferredRange['description'] ?? $fallbackRange['description'] ?? null,
            ], function ($value) {
                return $value !== null && $value !== '';
            });
        };

        $chapters = $primary['chapters'] ?? [];
        if ($chapters === [] && isset($fallback['chapters'])) {
            $chapters = $fallback['chapters'];
        }

        $payload = [
            'intro' => $mergeRange($primary['intro'] ?? null, $fallback['intro'] ?? null),
            'recap' => $mergeRange($primary['recap'] ?? null, $fallback['recap'] ?? null),
            'credits' => $mergeRange($primary['credits'] ?? null, $fallback['credits'] ?? null),
            'chapters' => is_array($chapters) ? array_values($chapters) : [],
        ];

        return $payload['intro'] || $payload['recap'] || $payload['credits'] || $payload['chapters'] !== []
            ? $payload
            : null;
    }

    private function formatPreferencePayload(?PlayerPreference $preference): array
    {
        return [
            'autoplayNextEpisode' => $preference?->autoplay_next_episode ?? true,
            'preferredSubtitle' => $preference?->preferred_subtitle,
            'preferredSubtitleEnabled' => $preference?->preferred_subtitle_enabled ?? false,
            'preferredQuality' => $preference?->preferred_quality ?? 'auto',
            'volume' => $preference?->volume ?? 1.0,
            'muted' => $preference?->muted ?? false,
            'theaterMode' => $preference?->theater_mode ?? false,
            'keyboardShortcutsEnabled' => $preference?->keyboard_shortcuts_enabled ?? true,
        ];
    }

    private function guessDeviceType(?string $userAgent): string
    {
        $agent = strtolower((string) $userAgent);

        if ($agent === '') {
            return 'unknown';
        }

        if (str_contains($agent, 'ipad') || str_contains($agent, 'tablet')) {
            return 'tablet';
        }

        if (str_contains($agent, 'mobile') || str_contains($agent, 'iphone') || str_contains($agent, 'android')) {
            return 'mobile';
        }

        return 'desktop';
    }

    private function shouldPreferBrowserSafePlayback(Request $request): bool
    {
        $secFetchDest = strtolower((string) $request->header('Sec-Fetch-Dest', ''));
        $secFetchMode = strtolower((string) $request->header('Sec-Fetch-Mode', ''));
        $accept = strtolower((string) $request->header('Accept', ''));
        $userAgent = strtolower((string) $request->userAgent());

        if ($secFetchDest !== '' || $secFetchMode !== '') {
            return true;
        }

        if (str_contains($accept, 'text/html')) {
            return true;
        }

        return str_contains($userAgent, 'mozilla/');
    }

    private function selectPreferredVideoSource(array $sources, bool $preferBrowserSafePlayback): ?array
    {
        if ($sources === []) {
            return null;
        }

        if (! $preferBrowserSafePlayback) {
            foreach ($sources as $source) {
                if (! empty($source['isPrimary'])) {
                    return is_array($source) ? $source : null;
                }
            }

            return is_array($sources[0]) ? $sources[0] : null;
        }

        foreach ($sources as $source) {
            if (! is_array($source)) {
                continue;
            }
            if (! empty($source['isPrimary']) && $this->isBrowserCompatibleVideoSource($source)) {
                return $source;
            }
        }

        foreach ($sources as $source) {
            if (is_array($source) && $this->isBrowserCompatibleVideoSource($source)) {
                return $source;
            }
        }

        foreach ($sources as $source) {
            if (! empty($source['isPrimary']) && is_array($source)) {
                return $source;
            }
        }

        return is_array($sources[0]) ? $sources[0] : null;
    }

    private function isAllowedPlaybackQuality(array $source, bool $allow720p = false): bool
    {
        $quality = strtolower((string) ($source['quality'] ?? $source['label'] ?? $source['id'] ?? ''));

        if (str_contains($quality, '1080') || str_contains($quality, '4k')) {
            return false;
        }

        if (str_contains($quality, '720')) {
            return $allow720p || ! $this->isManagedAdaptivePlaybackSource($source);
        }

        return true;
    }

    private function isManagedAdaptivePlaybackSource(array $source): bool
    {
        $type = strtolower((string) ($source['type'] ?? ''));
        $format = strtolower((string) ($source['format'] ?? ''));
        $url = strtolower((string) ($source['url'] ?? ''));

        return $type === 'nbx-engine'
            || in_array($format, ['hls', 'm3u8'], true)
            || str_contains($url, '.m3u8');
    }

    private function playbackSourceSortScore(array $source): int
    {
        $type = strtolower((string) ($source['type'] ?? ''));
        $format = strtolower((string) ($source['format'] ?? ''));
        $quality = strtolower((string) ($source['quality'] ?? ''));
        $url = strtolower((string) ($source['url'] ?? ''));
        $isHls = in_array($format, ['hls', 'm3u8'], true) || str_contains($url, '.m3u8');
        $isPrimary = ! empty($source['isPrimary']);
        $score = 900;

        if ($type === 'nbx-engine' && $isHls && str_contains($quality, '480')) {
            $score = 10;
        } elseif ($type === 'nbx-engine' && $isHls && str_contains($quality, '720')) {
            $score = 20;
        } elseif ($type === 'nbx-engine' && ! $isHls && (str_contains($quality, 'fast') || str_contains($url, 'fast'))) {
            $score = 30;
        } elseif ($type === 'nbx-engine' && ! $isHls) {
            $score = 40;
        } elseif (in_array($type, ['contabo', 'contabo_object_storage', 'tele_ob'], true)) {
            $score = 50;
        } elseif (in_array($type, ['url', 'direct', 'upload', 'uploaded', 'local', 'fetched', 'curl', 'cdn', 'legacy_cdn'], true)) {
            $score = 60;
        } elseif ($type === 'bunny_stream') {
            $score = 70;
        }

        return $isPrimary ? max(1, $score - 5) : $score;
    }

    private function filterPlaybackQualities(array $qualities, bool $allow720p = false): array
    {
        return array_values(array_filter($qualities, function (mixed $quality) use ($allow720p): bool {
            return is_array($quality) && $this->isAllowedPlaybackQuality($quality, $allow720p);
        }));
    }

    private function lockedPlaybackQualities(array $qualities, bool $allow720p = false): array
    {
        if ($allow720p) {
            return [];
        }

        return array_values(array_filter(array_map(function (mixed $quality): ?array {
            if (! is_array($quality) || ! $this->is720pQuality($quality)) {
                return null;
            }

            return [
                'quality' => strtolower((string) ($quality['id'] ?? $quality['label'] ?? '720p')),
                'label' => (string) ($quality['label'] ?? '720P'),
                'reason' => 'Upgrade to a weekly or monthly plan for 720p streaming.',
            ];
        }, $qualities)));
    }

    private function is720pQuality(array $quality): bool
    {
        $value = strtolower((string) ($quality['quality'] ?? $quality['label'] ?? $quality['id'] ?? ''));

        return str_contains($value, '720');
    }

    private function canStream720p($user): bool
    {
        if (! $user) {
            return false;
        }

        $activeSubscriptions = \App\Models\UserSubscription::query()
            ->with('subscriptionPlan')
            ->where('user_id', $user->id)
            ->where('status', 'ACTIVE')
            ->where('expires_at', '>', now())
            ->get();

        foreach ($activeSubscriptions as $subscription) {
            $durationDays = (int) ($subscription->subscriptionPlan?->duration_days ?? 0);
            $planName = strtolower((string) ($subscription->subscriptionPlan?->name ?? $user->plan ?? ''));
            $planSlug = strtolower((string) ($subscription->subscriptionPlan?->slug ?? ''));

            if ($durationDays >= 7 || str_contains($planName, 'weekly') || str_contains($planName, 'monthly') || str_contains($planSlug, 'weekly') || str_contains($planSlug, 'monthly')) {
                return true;
            }
        }

        $legacyPlan = strtolower((string) ($user->plan ?? ''));

        return in_array($legacyPlan, ['weekly', 'monthly'], true)
            || str_contains($legacyPlan, 'weekly')
            || str_contains($legacyPlan, 'monthly');
    }

    private function mp4OnlyUrl(mixed $url): ?string
    {
        if (! is_string($url) || trim($url) === '') {
            return null;
        }

        $path = strtolower((string) parse_url($url, PHP_URL_PATH));

        return str_ends_with($path, '.m3u8') ? null : $url;
    }

    private function orderVideoSourcesForBrowser(array $sources): array
    {
        usort($sources, function ($a, $b) {
            $aCompatible = is_array($a) ? $this->isBrowserCompatibleVideoSource($a) : false;
            $bCompatible = is_array($b) ? $this->isBrowserCompatibleVideoSource($b) : false;

            if ($aCompatible !== $bCompatible) {
                return $aCompatible ? -1 : 1;
            }

            $aPrimary = is_array($a) ? ! empty($a['isPrimary']) : false;
            $bPrimary = is_array($b) ? ! empty($b['isPrimary']) : false;

            if ($aPrimary === $bPrimary) {
                return 0;
            }

            return $aPrimary ? -1 : 1;
        });

        return $sources;
    }

    private function isBrowserCompatibleVideoSource(array $source): bool
    {
        $format = strtolower((string) ($source['format'] ?? ''));
        if (in_array($format, ['hls', 'm3u8', 'mp4', 'webm'], true)) {
            return true;
        }

        $url = strtolower((string) ($source['url'] ?? ''));
        if ($url === '') {
            return false;
        }

        return str_contains($url, '.m3u8') || str_contains($url, '.mp4') || str_contains($url, '.webm');
    }
}
