<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HeroSlide;
use App\Models\Movie;
use App\Models\TVShow;
use Illuminate\Http\Request;

/**
 * @group Hero
 *
 * Homepage carousel slides (each slide is a movie).
 */
class HeroController extends Controller
{
    /**
     * Get hero slides for homepage
     */
    public function index()
    {
        $heroSlides = HeroSlide::where('is_active', true)
            ->with(['media' => function ($query) {
                $query->with(['genres', 'vj', 'mediaLibrary', 'category']);
            }])
            ->orderBy('order')
            ->get();

        // TV shows are still selectable through legacy Movie mirror rows in
        // HeroSlide. Resolve those mirrors to the canonical TVShow record once,
        // so every client receives the correct entity id, slug and media type.
        $seriesSlugs = $heroSlides
            ->map(fn ($slide) => $slide->media)
            ->filter(fn ($media) => $media && strtoupper((string) $media->media_type) === 'SERIES')
            ->pluck('slug')
            ->filter()
            ->unique()
            ->values();

        $tvShowsBySlug = TVShow::query()
            ->where('is_active', true)
            ->whereIn('slug', $seriesSlugs)
            ->with(['genres', 'vj', 'mediaLibrary', 'category'])
            ->get()
            ->keyBy('slug');

        $slides = $heroSlides
            ->map(function ($slide) use ($tvShowsBySlug) {
                $legacyMedia = $slide->media;
                if (! $legacyMedia || ! $legacyMedia->is_active) {
                    return null;
                }

                $isSeries = strtoupper((string) $legacyMedia->media_type) === 'SERIES';
                $media = $isSeries ? $tvShowsBySlug->get($legacyMedia->slug) : $legacyMedia;

                // A stale series mirror must never be advertised as a movie.
                // Omitting it is safer than opening a source-less Movie record.
                if (! $media) {
                    return null;
                }
                
                // Helper to get full URL for images
                $getImageUrl = function ($path) {
                    if (empty($path)) return null;
                    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                        return $path;
                    }
                    return asset('storage/' . $path);
                };

                return [
                    'slide_id' => $slide->id,
                    'id' => $media->id,
                    'media_id' => $media->id,
                    'slug' => $media->slug,
                    'media_slug' => $media->slug,
                    'title' => $media->title,
                    'description' => $media->description,
                    'thumbnail' => $getImageUrl($media->thumbnail),
                    'backdrop' => $getImageUrl($media->backdrop),
                    'rating' => (float) $media->rating,
                    'releaseDate' => $media->release_date?->format('Y-m-d'),
                    'category' => $media->category?->name,
                    'mediaType' => $isSeries ? 'TV_SHOW' : 'MOVIE',
                    'media_type' => $isSeries ? 'TV_SHOW' : 'MOVIE',
                    'vj' => $media->vj?->name,
                    'creator' => $this->formatCreator($media),
                    'genre' => $media->genres->pluck('name')->toArray(),
                    'trendingScore' => $media->trending_score,
                    'accessType' => $media->access_type,
                    'videoUrl' => $isSeries ? null : $media->video_url,
                    'priceRent' => $media->price_rent ? (int) $media->price_rent : null,
                    'priceBuy' => $media->price_buy ? (int) $media->price_buy : null,
                ];
            })
            ->filter()
            ->values();

        return response()->json([
            'data' => $slides,
        ]);
    }

    private function formatCreator(Movie|TVShow $movie): ?array
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
                'type' => 'media_library',
                'name' => $movie->mediaLibrary->name,
                'slug' => $movie->mediaLibrary->slug,
                'isVerified' => (bool) $movie->mediaLibrary->is_verified,
                'image' => $getImageUrl($movie->mediaLibrary->image),
            ];
        }
        if ($movie->vj) {
            return [
                'type' => 'vj',
                'name' => $movie->vj->name,
                'slug' => $movie->vj->slug,
                'isVerified' => (bool) $movie->vj->is_verified,
                'image' => $getImageUrl($movie->vj->image),
            ];
        }
        return null;
    }
}
