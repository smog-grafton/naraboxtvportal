<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HeroSlide;
use App\Models\Movie;
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
        $slides = HeroSlide::where('is_active', true)
            ->with(['media' => function ($query) {
                $query->with(['genres', 'vj', 'mediaLibrary', 'category']);
            }])
            ->orderBy('order')
            ->get()
            ->map(function ($slide) {
                $movie = $slide->media;
                if (!$movie || !$movie->is_active) {
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
                    'creator' => $this->formatCreator($movie),
                    'genre' => $movie->genres->pluck('name')->toArray(),
                    'trendingScore' => $movie->trending_score,
                    'accessType' => $movie->access_type,
                    'videoUrl' => $movie->video_url,
                    'priceRent' => $movie->price_rent ? (int) $movie->price_rent : null,
                    'priceBuy' => $movie->price_buy ? (int) $movie->price_buy : null,
                ];
            })
            ->filter()
            ->values();

        return response()->json([
            'data' => $slides,
        ]);
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
