<?php

use App\Http\Controllers\Api\AccessController;
use App\Http\Controllers\Api\ActorController;
use App\Http\Controllers\Api\ArticleController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BannerController;
use App\Http\Controllers\Api\BootstrapController;
use App\Http\Controllers\Api\CdnFetchProxyController;
use App\Http\Controllers\Api\CmsPageController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\ContentRequestController;
use App\Http\Controllers\Api\Creator\CreatorAnalyticsController;
use App\Http\Controllers\Api\Creator\CreatorDashboardController;
use App\Http\Controllers\Api\Creator\CreatorFinanceController;
use App\Http\Controllers\Api\Creator\CreatorFormOptionsController;
use App\Http\Controllers\Api\Creator\CreatorMovieController;
use App\Http\Controllers\Api\Creator\CreatorPayoutMethodController;
use App\Http\Controllers\Api\Creator\CreatorProfileController;
use App\Http\Controllers\Api\Creator\CreatorSeasonController;
use App\Http\Controllers\Api\Creator\CreatorSourceController;
use App\Http\Controllers\Api\Creator\CreatorSubtitleController;
use App\Http\Controllers\Api\Creator\CreatorSupportController;
use App\Http\Controllers\Api\Creator\CreatorTmdbController;
use App\Http\Controllers\Api\Creator\CreatorTVShowController;
use App\Http\Controllers\Api\Creator\CreatorWithdrawalController;
use App\Http\Controllers\Api\CreatorController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DiscoveryController;
use App\Http\Controllers\Api\FlutterwaveController;
use App\Http\Controllers\Api\HeroController;
use App\Http\Controllers\Api\IoTeCController;
use App\Http\Controllers\Api\LiveStreamController;
use App\Http\Controllers\Api\MarketingPreferenceController;
use App\Http\Controllers\Api\MediaLibraryController;
use App\Http\Controllers\Api\MovieController;
use App\Http\Controllers\Api\NbxStorageEventController;
use App\Http\Controllers\Api\NbxWebhookController;
use App\Http\Controllers\Api\Partner\PartnerController;
use App\Http\Controllers\Api\PartnerBenefitController;
use App\Http\Controllers\Api\Partner\PartnerPayoutController;
use App\Http\Controllers\Api\PawaPayController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PlaybackReportController;
use App\Http\Controllers\Api\PlayerController;
use App\Http\Controllers\Api\PresenceController;
use App\Http\Controllers\Api\PushDeviceController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\SubtitleFetchController;
use App\Http\Controllers\Api\TelegramIngestNotifyController;
use App\Http\Controllers\Api\TeletydeStorageEventController;
use App\Http\Controllers\Api\TrendingController;
use App\Http\Controllers\Api\TvController;
use App\Http\Controllers\Api\TVShowController;
use App\Http\Controllers\Api\UserNotificationController;
use App\Http\Controllers\Api\VideoFetchController;
use App\Http\Controllers\Api\VJController;
use App\Http\Controllers\Api\WorkerSyncController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    $startedAt = microtime(true);

    try {
        DB::select('SELECT 1');

        return response()->json([
            'status' => 'ok',
            'service' => 'narabox-portal',
            'database' => 'reachable',
            'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'checked_at' => now()->toIso8601String(),
        ])->header('Cache-Control', 'no-store');
    } catch (Throwable) {
        return response()->json([
            'status' => 'degraded',
            'service' => 'narabox-portal',
            'database' => 'unreachable',
            'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'checked_at' => now()->toIso8601String(),
        ], 503)->header('Cache-Control', 'no-store');
    }
})->middleware('throttle:60,1');

Route::post('/cdn/fetch-and-push', [CdnFetchProxyController::class, 'fetchAndPush'])
    ->middleware('throttle:20,1');

Route::post('/telegram/ingest-notify', [TelegramIngestNotifyController::class, 'notify'])
    ->middleware('throttle:30,1');

Route::post('/v1/teletyde/storage-events', [TeletydeStorageEventController::class, 'handle'])
    ->middleware('throttle:240,1');

Route::post('/nbx/webhook', [NbxWebhookController::class, 'handle'])
    ->middleware('throttle:120,1');

Route::post('/v1/nbx/webhook', [NbxWebhookController::class, 'handle'])
    ->middleware('throttle:120,1');

Route::post('/v1/nbx/storage-events', [NbxStorageEventController::class, 'handle'])
    ->middleware('throttle:120,1');

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Worker sync (Bearer PORTAL_WORKER_API_TOKEN; same value as worker's PORTAL_API_TOKEN)
Route::post('/v1/worker/sync', [WorkerSyncController::class, 'sync'])
    ->middleware(['worker.api', 'throttle:60,1']);

// Public API routes (app-facing; protected by API key middleware when enabled)
Route::prefix('v1')->middleware(['app.api_key', 'platform.operations'])->group(function () {
    Route::get('/bootstrap', BootstrapController::class)->middleware('throttle:120,1');
    Route::post('/presence/heartbeat', [PresenceController::class, 'heartbeat'])
        ->middleware('throttle:20,1');
    // Authentication routes
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);
    Route::post('/auth/phone/request-otp', [AuthController::class, 'requestPhoneOtp']);
    Route::post('/auth/phone/verify-otp', [AuthController::class, 'verifyPhoneOtp']);
    Route::post('/auth/google/mobile', [AuthController::class, 'googleMobile']);
    Route::post('/auth/apple/mobile', [AuthController::class, 'appleMobile']);
    Route::post('/auth/verify-email', [AuthController::class, 'verifyEmail']);
    Route::post('/auth/resend-verification', [AuthController::class, 'resendVerificationCode']);
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);
    Route::post('/auth/web-bridge/consume', [AuthController::class, 'consumeWebBridgeToken']);
    Route::get('/auth/google/url', [AuthController::class, 'getGoogleAuthUrl']);
    Route::get('/auth/google', [AuthController::class, 'redirectToGoogle']);
    Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback']);
    Route::post('/tv/auth/device-code', [TvController::class, 'issueDeviceCode']);
    Route::post('/tv/auth/device-code/poll', [TvController::class, 'pollDeviceCode']);
    Route::post('/tv/auth/device-code/inspect', [TvController::class, 'inspectDeviceCode'])
        ->middleware('throttle:30,1');
    Route::get('/tv/home', [TvController::class, 'home']);

    // Shared time-windowed ranking for web, mobile, and TV clients.
    Route::get('/trending', TrendingController::class)->middleware('throttle:120,1');

    // Hero section
    Route::get('/hero', [HeroController::class, 'index']);

    // Movies
    Route::get('/movies', [MovieController::class, 'index']);
    Route::get('/movies/selected-today', [MovieController::class, 'selectedToday']);
    Route::get('/movies/{id}', [MovieController::class, 'show']);
    Route::get('/movies/{id}/similar', [MovieController::class, 'similar']);

    // TV Shows
    Route::get('/tv-shows', [TVShowController::class, 'index']);
    Route::get('/tv-shows/{id}', [TVShowController::class, 'show']);
    Route::get('/tv-shows/{id}/similar', [TVShowController::class, 'similar']);

    // Search (comprehensive search across all content types)
    Route::get('/search', [SearchController::class, 'search']);
    Route::get('/discovery/release-years', [DiscoveryController::class, 'releaseYears']);
    Route::get('/discovery/by-release-year', [DiscoveryController::class, 'byReleaseYear']);
    Route::get('/discovery/latest-by-release-date', [DiscoveryController::class, 'latestByReleaseDate']);

    // Genres (public list for creator applications and filters)
    Route::get('/genres', function () {
        return response()->json([
            'data' => \App\Models\Genre::select('id', 'name', 'slug')->orderBy('name')->get(),
        ]);
    });

    // Categories (public list for creator movie/TV form)
    Route::get('/categories', function () {
        return response()->json([
            'data' => \App\Models\Category::select('id', 'name', 'slug')->orderBy('name')->get(),
        ]);
    });

    Route::get('/editorial-categories', function () {
        return response()->json([
            'data' => \App\Models\EditorialCategory::query()
                ->where('is_active', true)
                ->select('id', 'name', 'slug', 'color', 'description')
                ->orderBy('name')
                ->get(),
        ]);
    });

    // VJs
    Route::get('/vjs', [VJController::class, 'index']);
    Route::get('/vjs/{id}', [VJController::class, 'show']);

    // Public partner referral resolution. The frontend stores the returned
    // attribution context until the viewer completes registration.
    Route::get('/partners/discovery', [PartnerController::class, 'discovery'])
        ->middleware('throttle:120,1');
    Route::get('/partners/referral-code-assistance', [PartnerController::class, 'referralCodeAssistance'])
        ->middleware('throttle:60,1');
    Route::get('/partner/referral/{code}', [PartnerController::class, 'resolve'])
        ->where('code', '[A-Za-z0-9_-]+');
    Route::post('/partner/referral/{code}/visit', [PartnerController::class, 'visit'])
        ->where('code', '[A-Za-z0-9_-]+')
        ->middleware('throttle:30,1');

    // Media Libraries (creator channels)
    Route::get('/media-libraries', [MediaLibraryController::class, 'index']);
    Route::get('/media-libraries/{id}', [MediaLibraryController::class, 'show']);

    // Articles/News
    Route::get('/articles', [ArticleController::class, 'index']);
    Route::get('/articles/{id}', [ArticleController::class, 'show']);

    // Contact
    Route::post('/contact', [ContactController::class, 'store']);
    Route::post('/content-requests', [ContentRequestController::class, 'store'])->middleware('throttle:10,1');
    Route::post('/playback/report', [PlaybackReportController::class, 'store'])->middleware('throttle:30,1');
    Route::get('/notifications/preferences/unsubscribe', [MarketingPreferenceController::class, 'unsubscribe'])->middleware('throttle:20,1');
    Route::post('/notifications/preferences/unsubscribe', [MarketingPreferenceController::class, 'unsubscribe'])->middleware('throttle:20,1');

    // CMS / legal pages (public read)
    Route::get('/pages', [CmsPageController::class, 'index']);
    Route::get('/pages/{slug}', [CmsPageController::class, 'show'])
        ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*');

    // Ad banners (public read-only)
    Route::get('/banners', [BannerController::class, 'index']);

    // Live Streams
    Route::get('/live-streams', [LiveStreamController::class, 'index']);
    Route::get('/live-streams/{id}', [LiveStreamController::class, 'show']);

    // Actors
    Route::get('/actors', [ActorController::class, 'index']);
    Route::get('/actors/trending', [ActorController::class, 'trending']);
    Route::get('/actors/{id}', [ActorController::class, 'show']);

    // Player (optional auth - works for both logged in and non-logged in users)
    // Removed auth:sanctum middleware to allow unauthenticated access to free content
    Route::get('/player/{id}', [PlayerController::class, 'show']);
    Route::post('/playback/sessions', [PlayerController::class, 'syncSession']);
    // Downloads (optional auth - allows free content downloads without login)
    // Removed auth:sanctum middleware - DownloadController handles access control internally
    Route::post('/downloads/{id}/authorize', [\App\Http\Controllers\Api\DownloadController::class, 'authorizeDownload']);
    Route::get('/downloads/{id}', [\App\Http\Controllers\Api\DownloadController::class, 'download'])
        ->name('downloads.file');

    // Payment gateways (public list)
    Route::get('/payment-gateways', [PaymentController::class, 'gateways']);

    // Subscription plans (public list)
    Route::get('/subscription-plans', [SubscriptionController::class, 'plans']);
    Route::get('/subscription-plans/{id}', [SubscriptionController::class, 'show']);

    // Comments (public read, auth required for write)
    Route::get('/comments/{mediaId}', [CommentController::class, 'index']);

    // Access checking (optional auth - allows checking free content without login)
    // Moved outside auth group so unauthenticated users can check free content
    Route::post('/access/check', [AccessController::class, 'checkAccess']);

    // View tracking (public - anyone can track views)
    Route::post('/views/track', [\App\Http\Controllers\Api\ViewController::class, 'track']);

    // Public creator-programme choices. The listed profiles are already exposed
    // through the public VJ and media-library directories.
    Route::get('/creator/onboarding/options', [CreatorController::class, 'options']);

    // Protected routes (require authentication)
    Route::middleware(['auth:sanctum', 'account.security'])->group(function () {
        // Auth
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
        Route::post('/auth/change-password', [AuthController::class, 'changePassword']);
        Route::delete('/auth/account', [AuthController::class, 'deleteAccount']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/auth/web-bridge-token', [AuthController::class, 'issueWebBridgeToken']);
        Route::post('/tv/auth/device-code/activate', [TvController::class, 'activateDeviceCode']);
        Route::post('/tv/checkout/sessions', [TvController::class, 'createCheckoutSession']);
        Route::get('/tv/checkout/sessions/{uuid}', [TvController::class, 'showCheckoutSession']);
        Route::post('/tv/checkout/sessions/{uuid}/attach-transaction', [TvController::class, 'attachCheckoutTransaction']);

        // Push devices (associate token with user when logged in)
        Route::post('/push/devices/register', [PushDeviceController::class, 'register']);
        Route::post('/push/devices/unregister', [PushDeviceController::class, 'unregister']);
        Route::get('/notifications', [UserNotificationController::class, 'index']);
        Route::post('/notifications/read-all', [UserNotificationController::class, 'readAll']);
        Route::post('/notifications/{notification}/read', [UserNotificationController::class, 'markAsRead']);
        Route::get('/player-preferences', [PlayerController::class, 'getPreferences']);
        Route::put('/player-preferences', [PlayerController::class, 'updatePreferences']);
        Route::get('/content-requests/mine', [ContentRequestController::class, 'mine']);

        // Creator application (upgrade to VJ or Media Library)
        Route::prefix('creator')->group(function () {
            Route::get('/application', [CreatorController::class, 'getApplication']);
            Route::post('/apply', [CreatorController::class, 'apply']);
            Route::put('/application', [CreatorController::class, 'updateApplication']);
            Route::post('/application/submit', [CreatorController::class, 'submitApplication']);
            Route::post('/claims', [CreatorController::class, 'startClaim']);
            Route::get('/claims', [CreatorController::class, 'getClaimStatus']);
            Route::post('/claim-vj', [CreatorController::class, 'claimVj']);
            Route::get('/claim-vj', [CreatorController::class, 'getClaimStatus']);
            Route::post('/evidence', [CreatorController::class, 'uploadEvidence']);
            Route::get('/evidence/{evidence}', [CreatorController::class, 'downloadEvidence'])
                ->name('creator.evidence.download')
                ->middleware('throttle:30,1');
            Route::post('/information-requests/{informationRequest}/responses', [CreatorController::class, 'respondToInformationRequest']);
            Route::post('/application/information-responses/submit', [CreatorController::class, 'submitInformationResponses']);

            // Creator content portal
            Route::get('/overview', [CreatorDashboardController::class, 'overview']);
            Route::get('/form-options', CreatorFormOptionsController::class);
            Route::get('/profile', [CreatorProfileController::class, 'show']);
            Route::post('/profile/{type}/{id}', [CreatorProfileController::class, 'update'])
                ->whereIn('type', ['vj', 'media-library'])
                ->whereNumber('id');
            Route::get('/analytics', CreatorAnalyticsController::class);
            Route::get('/support', [CreatorSupportController::class, 'index']);
            Route::post('/support', [CreatorSupportController::class, 'store'])
                ->middleware('throttle:10,1');

            // Movies CRUD
            Route::get('/movies', [CreatorMovieController::class, 'index']);
            Route::post('/movies', [CreatorMovieController::class, 'store']);
            Route::get('/movies/{id}', [CreatorMovieController::class, 'show']);
            Route::put('/movies/{id}', [CreatorMovieController::class, 'update']);
            Route::post('/movies/{id}', [CreatorMovieController::class, 'update']); // FormData support
            Route::delete('/movies/{id}', [CreatorMovieController::class, 'destroy']);
            Route::post('/movies/{id}/publish', [CreatorMovieController::class, 'publish']);

            // Movie sources
            Route::get('/movies/{id}/sources', [CreatorSourceController::class, 'indexForMovie']);
            Route::post('/movies/{id}/sources', [CreatorSourceController::class, 'storeForMovie']);
            Route::get('/movies/{id}/cdn-upload-token', [CreatorSourceController::class, 'cdnUploadToken']);
            Route::get('/movies/{id}/subtitles', [CreatorSubtitleController::class, 'indexForMovie']);
            Route::post('/movies/{id}/subtitles', [CreatorSubtitleController::class, 'storeForMovie']);
            Route::get('/submissions/{submissionId}', [CreatorSourceController::class, 'submissionStatus'])
                ->whereUuid('submissionId');
            Route::post('/submissions/{submissionId}/retry', [CreatorSourceController::class, 'retry'])
                ->whereUuid('submissionId');

            // Source status and delete
            Route::get('/sources/{id}/status', [CreatorSourceController::class, 'status']);
            Route::delete('/sources/{id}', [CreatorSourceController::class, 'destroy']);

            // TV Shows CRUD
            Route::get('/tv-shows', [CreatorTVShowController::class, 'index']);
            Route::post('/tv-shows', [CreatorTVShowController::class, 'store']);
            Route::get('/tv-shows/{id}', [CreatorTVShowController::class, 'show']);
            Route::put('/tv-shows/{id}', [CreatorTVShowController::class, 'update']);
            Route::post('/tv-shows/{id}', [CreatorTVShowController::class, 'update']); // FormData support
            Route::delete('/tv-shows/{id}', [CreatorTVShowController::class, 'destroy']);
            Route::post('/tv-shows/{id}/publish', [CreatorTVShowController::class, 'publish']);
            Route::get('/tv-shows/{id}/seasons', [CreatorTVShowController::class, 'seasons']);
            Route::post('/tv-shows/{id}/seasons/import', [CreatorSeasonController::class, 'importFromTmdb']);
            Route::post('/tv-shows/{id}/seasons', [CreatorSeasonController::class, 'store']);
            Route::put('/seasons/{id}', [CreatorSeasonController::class, 'update']);
            Route::delete('/seasons/{id}', [CreatorSeasonController::class, 'destroy']);
            Route::post('/seasons/{id}/episodes', [CreatorSeasonController::class, 'storeEpisode']);
            Route::post('/seasons/{id}/episodes/reorder', [CreatorSeasonController::class, 'reorderEpisodes']);
            Route::put('/episodes/{id}', [CreatorSeasonController::class, 'updateEpisode']);
            Route::post('/episodes/{id}/duplicate', [CreatorSeasonController::class, 'duplicateEpisode']);
            Route::post('/episodes/{id}/publish', [CreatorSeasonController::class, 'publishEpisode']);
            Route::delete('/episodes/{id}', [CreatorSeasonController::class, 'destroyEpisode']);
            Route::get('/episodes/{id}/sources', [CreatorSourceController::class, 'indexForEpisode']);
            Route::post('/episodes/{id}/sources', [CreatorSourceController::class, 'storeForEpisode']);
            Route::get('/episodes/{id}/cdn-upload-token', [CreatorSourceController::class, 'episodeUploadToken']);
            Route::get('/episodes/{id}/subtitles', [CreatorSubtitleController::class, 'indexForEpisode']);
            Route::post('/episodes/{id}/subtitles', [CreatorSubtitleController::class, 'storeForEpisode']);
            Route::delete('/subtitles/{id}', [CreatorSubtitleController::class, 'destroy']);

            // TMDB
            Route::get('/tmdb/search', [CreatorTmdbController::class, 'search']);
            Route::get('/tmdb/movie/{tmdbId}', [CreatorTmdbController::class, 'movie']);
            Route::get('/tmdb/tv/{tmdbId}', [CreatorTmdbController::class, 'tv']);
            Route::get('/tmdb/tv/{tmdbTvId}/season/{seasonNumber}', [CreatorTmdbController::class, 'tvSeason']);

            // Creator finance
            Route::get('/finance/summary', [CreatorFinanceController::class, 'summary']);
            Route::get('/finance/earnings', [CreatorFinanceController::class, 'earnings']);
            Route::get('/finance/ledger', [CreatorFinanceController::class, 'ledger']);
            Route::get('/payout-methods', [CreatorPayoutMethodController::class, 'index']);
            Route::post('/payout-methods', [CreatorPayoutMethodController::class, 'store']);
            Route::put('/payout-methods/{id}', [CreatorPayoutMethodController::class, 'update']);
            Route::delete('/payout-methods/{id}', [CreatorPayoutMethodController::class, 'destroy']);
            Route::post('/payout-methods/{id}/set-default', [CreatorPayoutMethodController::class, 'setDefault']);
            Route::get('/withdrawals', [CreatorWithdrawalController::class, 'index']);
            Route::post('/withdrawals', [CreatorWithdrawalController::class, 'store']);
            Route::delete('/withdrawals/{id}', [CreatorWithdrawalController::class, 'destroy']);
        });

        // Partner business portal. Financial values are returned by Laravel;
        // clients never calculate commissions locally.
        Route::prefix('partner')->group(function () {
            Route::get('/application', [PartnerController::class, 'application']);
            Route::post('/application', [PartnerController::class, 'apply']);
            Route::post('/attribute', [PartnerController::class, 'attribute']);
            Route::get('/dashboard', [PartnerController::class, 'dashboard']);
            Route::get('/analytics', [PartnerController::class, 'analytics']);
            Route::get('/referrals', [PartnerController::class, 'referrals']);
            Route::get('/transactions', [PartnerController::class, 'transactions']);
            Route::get('/earnings', [PartnerController::class, 'earnings']);
            Route::get('/profile', [PartnerController::class, 'profile']);
            Route::post('/profile', [PartnerController::class, 'updateProfile'])
                ->middleware('throttle:10,1');
            Route::get('/benefits', [PartnerController::class, 'benefits']);
            Route::put('/benefits/{code}', [PartnerController::class, 'updateBenefit'])
                ->where('code', '[A-Za-z0-9_-]+')
                ->middleware('throttle:20,1');
            Route::match(['get', 'post'], '/campaigns', [PartnerController::class, 'campaigns']);
            Route::post('/benefits/first-movie-free/claim', [PartnerBenefitController::class, 'claimFirstMovie'])
                ->middleware('throttle:10,1');
            Route::get('/payout-methods', [PartnerPayoutController::class, 'methods']);
            Route::post('/payout-methods', [PartnerPayoutController::class, 'storeMethod']);
            Route::get('/withdrawals', [PartnerPayoutController::class, 'withdrawals']);
            Route::post('/withdrawals', [PartnerPayoutController::class, 'requestWithdrawal']);
            Route::delete('/withdrawals/{id}', [PartnerPayoutController::class, 'cancelWithdrawal'])
                ->whereNumber('id');
        });

        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index']);

        // Payments (require email verification)
        Route::middleware('email.verified')->group(function () {
            Route::post('/payments/initiate', [PaymentController::class, 'initiate'])->middleware('payment.security');
            Route::post('/payments/upload-proof', [PaymentController::class, 'uploadProof']);
            Route::post('/payments/verify', [PaymentController::class, 'verify']);

            // Flutterwave routes
            Route::post('/flutterwave/initiate', [FlutterwaveController::class, 'initiate'])->middleware('payment.security');
            Route::post('/flutterwave/verify', [FlutterwaveController::class, 'verify']);

            // ioTec Pay routes (in-site phone prompt)
            Route::post('/iotec/initiate', [IoTeCController::class, 'initiate'])->middleware('payment.security');
            Route::get('/iotec/status', [IoTeCController::class, 'status']);
            Route::post('/iotec/status', [IoTeCController::class, 'status']);

            // PawaPay routes (in-site mobile money deposit)
            Route::post('/payments/pawapay/deposit/initiate', [PawaPayController::class, 'initiateDeposit'])->middleware('payment.security');
            Route::get('/payments/pawapay/deposit/{depositId}/status', [PawaPayController::class, 'checkDepositStatus']);
        });

        // Watch history
        Route::post('/watch-history', [PlayerController::class, 'updateHistory']);
        Route::get('/watch-history', [PlayerController::class, 'getHistory']);

        // Video Fetch (cURL fetch feature - admin only)
        Route::post('/video/fetch', [VideoFetchController::class, 'fetch']);

        // Subtitle Fetch (cURL fetch feature - admin only)
        Route::post('/subtitle/fetch', [SubtitleFetchController::class, 'fetch']);

        // Comments (write operations require auth)
        Route::post('/comments', [CommentController::class, 'store']);
        Route::post('/comments/{id}/like', [CommentController::class, 'toggleLike']);
        Route::delete('/comments/{id}', [CommentController::class, 'destroy']);
    });

});

// Provider callbacks use provider-specific signatures/tokens. They intentionally
// sit outside the public-client API-key and customer-session middleware because
// payment providers cannot send NaraBox client credentials.
Route::prefix('v1')->group(function () {
    Route::post('/flutterwave/webhook', [FlutterwaveController::class, 'webhook'])
        ->middleware('throttle:120,1');
    Route::post('/iotec/webhook', [IoTeCController::class, 'webhook'])
        ->middleware('throttle:120,1');
    Route::post('/webhooks/pawapay/deposits', [PawaPayController::class, 'depositWebhook'])
        ->middleware('throttle:120,1');
    Route::post('/webhooks/pawapay/refunds', [PawaPayController::class, 'refundWebhook'])
        ->middleware('throttle:60,1');
});
