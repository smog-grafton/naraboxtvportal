<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Movie;
use App\Models\VJ;
use App\Models\Article;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function search(Request $request)
    {
        $query = $request->get('q', '');
        
        if (empty($query)) {
            return response()->json([
                'data' => [
                    'archives' => [],
                    'people' => [],
                    'intel' => []
                ]
            ]);
        }

        // Helper to get full URL for images
        $getImageUrl = function ($path) {
            if (empty($path)) return null;
            if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                return $path;
            }
            return asset('storage/' . $path);
        };

        // Search Movies and TV Shows (Archives)
        $archives = Movie::where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%");
            })
            ->with(['genres', 'vj', 'category'])
            ->limit(20)
            ->get()
            ->map(function ($movie) use ($getImageUrl) {
                return [
                    'id' => (string) $movie->id,
                    'title' => $movie->title,
                    'description' => $movie->description,
                    'thumbnail' => $getImageUrl($movie->thumbnail),
                    'backdrop' => $getImageUrl($movie->backdrop),
                    'rating' => (float) $movie->rating,
                    'releaseDate' => $movie->release_date->format('Y-m-d'),
                    'category' => $movie->category->name ?? '',
                    'mediaType' => $movie->media_type,
                    'vj' => $movie->vj ? $movie->vj->name : null,
                    'genre' => $movie->genres->pluck('name')->toArray(),
                    'trendingScore' => $movie->trending_score,
                    'accessType' => $movie->access_type,
                    'videoUrl' => $movie->video_url,
                    'duration' => $movie->duration,
                ];
            });

        // Search VJs (People)
        $people = VJ::where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('bio', 'like', "%{$query}%");
            })
            ->with('genres')
            ->limit(10)
            ->get()
            ->map(function ($vj) use ($getImageUrl) {
                return [
                    'id' => (string) $vj->id,
                    'name' => $vj->name,
                    'image' => $getImageUrl($vj->image),
                    'banner' => $getImageUrl($vj->banner),
                    'rating' => (float) $vj->rating,
                    'specialty' => $vj->genres->pluck('name')->toArray(),
                    'bio' => $vj->bio,
                    'translatedCount' => $vj->translated_count,
                ];
            });

        // Search Articles (Intel)
        $intel = Article::where('is_published', true)
            ->where(function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                  ->orWhere('excerpt', 'like', "%{$query}%");
            })
            ->limit(10)
            ->get()
            ->map(function ($article) use ($getImageUrl) {
                return [
                    'id' => (string) $article->id,
                    'title' => $article->title,
                    'excerpt' => $article->excerpt,
                    'author' => $article->author,
                    'image' => $getImageUrl($article->image),
                    'videoUrl' => $article->video_url,
                    'date' => $article->date->format('M d, Y'),
                    'category' => $article->category,
                    'isTopNews' => $article->is_top_news,
                ];
            });

        return response()->json([
            'data' => [
                'archives' => $archives->values()->all(),
                'people' => $people->values()->all(),
                'intel' => $intel->values()->all()
            ]
        ]);
    }
}

