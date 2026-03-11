<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Movie;
use App\Models\TVShow;
use App\Models\Episode;
use App\Models\WatchHistory;
use App\Services\CdnMediaClientService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PlayerController extends Controller
{
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

        $episode = null;
        if ($episodeId) {
            if ($isTVShow) {
                // For TV shows, find episode by season's tv_show_id
                // Use the actual TV show ID (not slug) for the query
                $tvShowId = $tvShow->id;
                $episode = Episode::where('id', $episodeId)
                    ->whereHas('season', function ($q) use ($tvShowId) {
                        $q->where('tv_show_id', $tvShowId);
                    })
                    ->first();
            } else {
                // For movies, find episode by season's media_id
                // Use the actual movie ID (not slug) for the query
                $movieId = is_object($movie) ? $movie->id : $movie;
                $episode = Episode::where('id', $episodeId)
                    ->whereHas('season', function ($q) use ($movieId) {
                        $q->where('media_id', $movieId);
                    })
                    ->first();
            }
        }

        // Resolve user from Bearer token (route has no auth middleware)
        $user = Auth::guard('sanctum')->user();
        
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
        $videoSourceModels = $sourceable->videoSources()
            ->where('is_active', true)
            ->orderBy('is_primary', 'desc')
            ->orderBy('quality', 'desc')
            ->get();

        $videoSources = $videoSourceModels
            ->map(function ($source) {
                return [
                    'id' => $source->id,
                    'url' => $source->full_url,
                    'quality' => $source->quality ?? 'auto',
                    'format' => $source->format ?? 'mp4',
                    'type' => $source->type,
                    'isPrimary' => $source->is_primary,
                    'duration' => $source->duration_seconds, // Include duration if available
                ];
            });
        
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
        $primarySource = $videoSources->firstWhere('isPrimary', true) ?? $videoSources->first();
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
            $apiUrl = config('app.url') . '/api/v1';
            $downloadSources = $sourceable->downloadSources()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get()
                ->map(function ($source) use ($apiUrl) {
                    // Use the download endpoint URL instead of direct file URL
                    return [
                        'id' => $source->id,
                        'type' => $source->type, // Include type: 'url', 'local', 'fetched'
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
            $cdnMediaClientService
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
            ] : null,
            'videoUrl' => $videoUrl,
            'videoSources' => $videoSources->values()->toArray(), // All available sources for quality switching
            'subtitles' => $subtitles->values()->toArray(), // All available subtitles
            'duration' => $duration, // Duration in seconds
            'poster' => $getImageUrl($isTVShow ? $tvShow->backdrop : $movie->backdrop) ?? $getImageUrl($isTVShow ? $tvShow->thumbnail : $movie->thumbnail), // Use backdrop as poster, fallback to thumbnail
            'downloadSources' => $downloadSources,
            'playback' => $playback,
        ]);
    }

    public function updateHistory(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $request->validate([
            'media_id' => 'required|exists:movies,id',
            'episode_id' => 'nullable|exists:episodes,id',
            'progress_seconds' => 'required|integer|min:0',
            'total_seconds' => 'nullable|integer|min:0',
        ]);

        WatchHistory::updateOrCreate(
            [
                'user_id' => $user->id,
                'media_id' => $request->media_id,
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

    public function getHistory(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $history = WatchHistory::where('user_id', $user->id)
            ->with(['media', 'episode'])
            ->orderBy('last_watched_at', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'mediaId' => $item->media_id,
                    'episodeId' => $item->episode_id,
                    'progressSeconds' => $item->progress_seconds,
                    'totalSeconds' => $item->total_seconds,
                    'lastWatched' => $item->last_watched_at->toIso8601String(),
                ];
            });

        return response()->json(['data' => $history]);
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

        // PRIORITY 1: For premium content, check subscription FIRST (subscription overrides everything)
        if ($isPremium) {
            // Check all subscriptions for debugging
            $allSubscriptions = \App\Models\UserSubscription::where('user_id', $user->id)->get();
            
            $activeSubscription = \App\Models\UserSubscription::where('user_id', $user->id)
                ->where('status', 'ACTIVE')
                ->where('expires_at', '>', now())
                ->first();

            // Debug: Log subscription check details
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

            // Premium content without subscription - check if user can rent/buy as alternative
            // But subscription is still required for premium content
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

        // PRIORITY 2: Check if user has purchased (permanent access)
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

        // PRIORITY 3: Check if user has active rental
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

        // PRIORITY 4: Check for pending payments
        $transactionType = $tvShow ? TVShow::class : Movie::class;
        $pendingTransaction = \App\Models\PaymentTransaction::where('user_id', $user->id)
            ->where('transactionable_type', $transactionType)
            ->where('transactionable_id', $movie->id)
            ->where('status', 'PENDING')
            ->first();

        if ($pendingTransaction) {
            return [
                'has_access' => false,
                'access_type' => 'PENDING',
                'reason' => 'Your payment is pending admin approval. Access will be granted once approved.',
                'pending_payment' => true,
                'transaction_ref' => $pendingTransaction->transaction_ref,
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

    private function buildPlaybackPayload(
        \Illuminate\Support\Collection $videoSourceModels,
        array $legacyVideoSources,
        array $subtitles,
        ?string $videoUrl,
        array $downloadSources,
        CdnMediaClientService $cdnMediaClientService
    ): ?array {
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
        ];

        if (! (bool) config('services.cdn.use_playback_manifest', true)) {
            return $defaultPlayback;
        }

        $fetchedSource = $videoSourceModels->first(function ($source) {
            return in_array($source->type, ['fetched', 'local', 'url'], true)
                && is_array($source->metadata)
                && ! empty($source->metadata['cdn_asset_id']);
        });

        if (! $fetchedSource || ! is_array($fetchedSource->metadata ?? null)) {
            return $defaultPlayback;
        }

        $sourceMetadata = (array) ($fetchedSource->metadata ?? []);
        $cdnAssetId = (string) ($fetchedSource->metadata['cdn_asset_id'] ?? '');
        if ($cdnAssetId === '') {
            $metaType = (string) ($sourceMetadata['playback_type'] ?? 'mp4');
            $metaHls = isset($sourceMetadata['hls_master_url']) ? (string) $sourceMetadata['hls_master_url'] : null;
            $metaMp4 = isset($sourceMetadata['mp4_play_url'])
                ? (string) $sourceMetadata['mp4_play_url']
                : (isset($sourceMetadata['mp4_url']) ? (string) $sourceMetadata['mp4_url'] : $videoUrl);
            $metaDownload = isset($sourceMetadata['download_url']) ? (string) $sourceMetadata['download_url'] : ($downloadSources[0]['download_url'] ?? null);
            $metaUrl = $metaType === 'hls' && is_string($metaHls) && $metaHls !== '' ? $metaHls : $metaMp4;

            return array_merge($defaultPlayback, [
                'type' => $metaType === 'hls' ? 'hls' : 'mp4',
                'url' => $metaUrl,
                'hls_master_url' => $metaHls,
                'mp4_play_url' => $metaMp4,
                'mp4_url' => $metaMp4,
                'download_url' => $metaDownload,
                'qualities' => is_array($sourceMetadata['qualities'] ?? null) ? $sourceMetadata['qualities'] : [],
            ]);
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

        return [
            'type' => $type,
            'url' => $playbackUrl,
            'hls_master_url' => $hlsMaster,
            'mp4_play_url' => $mp4PlayUrl,
            'mp4_url' => $mp4PlayUrl,
            'download_url' => $playback['download_url'] ?? ($downloadSources[0]['download_url'] ?? null),
            'sources' => $finalSources !== [] ? $finalSources : $legacyVideoSources,
            'subtitles' => $subtitles,
            'qualities' => $qualities,
        ];
    }
}
