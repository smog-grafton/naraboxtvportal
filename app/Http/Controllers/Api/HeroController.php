<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HeroSlide;
use App\Models\Movie;
use Illuminate\Http\Request;

class HeroController extends Controller
{
    public function index()
    {
        $slides = HeroSlide::where('is_active', true)
            ->with(['media' => function ($query) {
                $query->with(['genres', 'vj', 'category']);
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
                    'title' => $movie->title,
                    'description' => $movie->description,
                    'thumbnail' => $getImageUrl($movie->thumbnail),
                    'backdrop' => $getImageUrl($movie->backdrop),
                    'rating' => (float) $movie->rating,
                    'releaseDate' => $movie->release_date->format('Y-m-d'),
                    'category' => $movie->category->name,
                    'mediaType' => $movie->media_type,
                    'vj' => $movie->vj ? $movie->vj->name : null,
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
}
