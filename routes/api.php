<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\HeroController;
use App\Http\Controllers\Api\MovieController;
use App\Http\Controllers\Api\VJController;
use App\Http\Controllers\Api\ArticleController;
use App\Http\Controllers\Api\PlayerController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\AccessController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\VideoFetchController;
use App\Http\Controllers\Api\SubtitleFetchController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\ActorController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FlutterwaveController;
use App\Http\Controllers\Api\IoTeCController;
use App\Http\Controllers\Api\PawaPayController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\TVShowController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\LiveStreamController;
use App\Http\Controllers\Api\CdnFetchProxyController;
use App\Http\Controllers\Api\TelegramIngestNotifyController;
use App\Http\Controllers\Api\WorkerSyncController;

Route::post('/cdn/fetch-and-push', [CdnFetchProxyController::class, 'fetchAndPush'])
    ->middleware('throttle:20,1');

Route::post('/telegram/ingest-notify', [TelegramIngestNotifyController::class, 'notify'])
    ->middleware('throttle:30,1');

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Worker sync (Bearer PORTAL_WORKER_API_TOKEN; same value as worker's PORTAL_API_TOKEN)
Route::post('/v1/worker/sync', [WorkerSyncController::class, 'sync'])
    ->middleware(['worker.api', 'throttle:60,1']);

// Public API routes
Route::prefix('v1')->group(function () {
    // Authentication routes
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/verify-email', [AuthController::class, 'verifyEmail']);
    Route::post('/auth/resend-verification', [AuthController::class, 'resendVerificationCode']);
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);
    Route::get('/auth/google/url', [AuthController::class, 'getGoogleAuthUrl']);
    Route::get('/auth/google', [AuthController::class, 'redirectToGoogle']);
    Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback']);
    
    // Hero section
    Route::get('/hero', [HeroController::class, 'index']);
    
    // Movies
    Route::get('/movies', [MovieController::class, 'index']);
    Route::get('/movies/selected-today', [MovieController::class, 'selectedToday']);
    Route::get('/movies/{id}', [MovieController::class, 'show']);
    
    // TV Shows
    Route::get('/tv-shows', [TVShowController::class, 'index']);
    Route::get('/tv-shows/{id}', [TVShowController::class, 'show']);
    
    // Search (comprehensive search across all content types)
    Route::get('/search', [SearchController::class, 'search']);
    
    // VJs
    Route::get('/vjs', [VJController::class, 'index']);
    Route::get('/vjs/{id}', [VJController::class, 'show']);
    
    // Articles/News
    Route::get('/articles', [ArticleController::class, 'index']);
    Route::get('/articles/{id}', [ArticleController::class, 'show']);
    
    // Contact
    Route::post('/contact', [ContactController::class, 'store']);
    
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
    // Downloads (optional auth - allows free content downloads without login)
    // Removed auth:sanctum middleware - DownloadController handles access control internally
    Route::get("/downloads/{id}", [\App\Http\Controllers\Api\DownloadController::class, "download"]);
    
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
    
    // Protected routes (require authentication)
    Route::middleware('auth:sanctum')->group(function () {
        // Auth
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
        Route::delete('/auth/account', [AuthController::class, 'deleteAccount']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        
        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index']);
        
        // Payments (require email verification)
        Route::middleware('email.verified')->group(function () {
            Route::post('/payments/initiate', [PaymentController::class, 'initiate']);
            Route::post('/payments/upload-proof', [PaymentController::class, 'uploadProof']);
            Route::post('/payments/verify', [PaymentController::class, 'verify']);
            
            // Flutterwave routes
            Route::post('/flutterwave/initiate', [FlutterwaveController::class, 'initiate']);
            Route::post('/flutterwave/verify', [FlutterwaveController::class, 'verify']);
            
            // ioTec Pay routes (in-site phone prompt)
            Route::post('/iotec/initiate', [IoTeCController::class, 'initiate']);
            Route::get('/iotec/status', [IoTeCController::class, 'status']);
            Route::post('/iotec/status', [IoTeCController::class, 'status']);

            // PawaPay routes (in-site mobile money deposit)
            Route::post('/payments/pawapay/deposit/initiate', [PawaPayController::class, 'initiateDeposit']);
            Route::get('/payments/pawapay/deposit/{depositId}/status', [PawaPayController::class, 'checkDepositStatus']);
        });
        
        // Flutterwave webhook (no auth required, but should verify signature)
        Route::post('/flutterwave/webhook', [FlutterwaveController::class, 'webhook']);
        
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
    
    // ioTec webhook (no auth - called by ioTec when transaction status changes)
    Route::post('/iotec/webhook', [IoTeCController::class, 'webhook']);

    // PawaPay webhooks (no auth - callback from pawaPay)
    Route::post('/webhooks/pawapay/deposits', [PawaPayController::class, 'depositWebhook'])->middleware('throttle:120,1');
    Route::post('/webhooks/pawapay/refunds', [PawaPayController::class, 'refundWebhook'])->middleware('throttle:60,1');
});

