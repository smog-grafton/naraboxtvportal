<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;
use App\Http\Controllers\SeoController;

Route::get('/', function () {
    return view('welcome');
});

// SEO Routes - These serve SEO-optimized HTML for crawlers and social media
// They redirect to Next.js for actual user experience
Route::get('/movie/{slug}', [SeoController::class, 'movie'])->name('seo.movie');
Route::get('/movies/{slug}', [SeoController::class, 'movie'])->name('seo.movies'); // Alias for Next.js route structure
Route::get('/tv/{slug}', [SeoController::class, 'tv'])->name('seo.tv');
Route::get('/tv-shows/{slug}', [SeoController::class, 'tv'])->name('seo.tv-shows'); // Alias for Next.js route structure
Route::get('/vj/{slug}', [SeoController::class, 'vj'])->name('seo.vj');
Route::get('/vjs/{slug}', [SeoController::class, 'vj'])->name('seo.vjs'); // Alias for Next.js route structure
Route::get('/vjs', [SeoController::class, 'vjs'])->name('seo.vjs-listing');
Route::get('/news/{slug}', [SeoController::class, 'news'])->name('seo.news'); // Supports both slug and ID
Route::get('/news', [SeoController::class, 'newsListing'])->name('seo.news-listing');

// Listing page SEO routes
Route::get('/movies', [SeoController::class, 'movies'])->name('seo.movies');
Route::get('/tv-shows', [SeoController::class, 'tvShows'])->name('seo.tv-shows');

// Static page SEO routes
Route::get('/contact', [SeoController::class, 'contact'])->name('seo.contact');
Route::get('/about', [SeoController::class, 'about'])->name('seo.about');
Route::get('/help-center', [SeoController::class, 'helpCenter'])->name('seo.help-center');
Route::get('/device-support', [SeoController::class, 'deviceSupport'])->name('seo.device-support');
Route::get('/privacy-policy', [SeoController::class, 'privacyPolicy'])->name('seo.privacy-policy');
Route::get('/terms', [SeoController::class, 'terms'])->name('seo.terms');
Route::get('/live', [SeoController::class, 'live'])->name('seo.live');

// Serve storage files with CORS headers (more reliable than .htaccess)
Route::get('/storage/{path}', function ($path) {
    $filePath = storage_path('app/public/' . $path);
    
    // Security: prevent directory traversal
    $realPath = realpath($filePath);
    $storagePath = realpath(storage_path('app/public'));
    
    if (!$realPath || !str_starts_with($realPath, $storagePath) || !is_file($realPath)) {
        abort(404);
    }
    
    $mimeType = mime_content_type($realPath);
    
    return Response::file($realPath, [
        'Content-Type' => $mimeType,
    ])->withHeaders([
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, OPTIONS',
        'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, Accept, Origin',
        'Access-Control-Max-Age' => '86400',
        'Cache-Control' => 'public, max-age=31536000',
    ]);
})->where('path', '.*');

// Handle OPTIONS preflight for storage files
Route::options('/storage/{path}', function () {
    return response('', 200)->withHeaders([
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, OPTIONS',
        'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, Accept, Origin',
        'Access-Control-Max-Age' => '86400',
    ]);
})->where('path', '.*');
