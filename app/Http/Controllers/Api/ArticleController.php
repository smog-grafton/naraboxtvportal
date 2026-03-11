<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\Http\Request;

class ArticleController extends Controller
{
    public function index(Request $request)
    {
        $query = Article::where('is_published', true)
            ->with(['blocks', 'tags']);

        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        if ($request->has('top_news')) {
            $query->where('is_top_news', true);
        }

        $articles = $query->orderBy('date', 'desc')
            ->orderBy('is_top_news', 'desc')
            ->paginate($request->get('per_page', 10));

        return response()->json([
            'data' => $articles->map(fn($article) => $this->formatArticle($article)),
            'meta' => [
                'current_page' => $articles->currentPage(),
                'last_page' => $articles->lastPage(),
                'per_page' => $articles->perPage(),
                'total' => $articles->total(),
            ],
        ]);
    }

    public function show($id)
    {
        // Support both slug and ID (backward compatibility)
        $article = Article::where('is_published', true)
            ->with(['blocks', 'tags'])
            ->where(function ($query) use ($id) {
                $query->where('id', $id)
                      ->orWhere('slug', $id);
            })
            ->firstOrFail();

        return response()->json($this->formatArticle($article));
    }

    private function formatArticle(Article $article): array
    {
        // Helper to get full URL for images
        $getImageUrl = function ($path) {
            if (empty($path)) return null;
            if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                return $path;
            }
            return asset('storage/' . $path);
        };

        return [
            'id' => $article->id,
            'slug' => $article->slug,
            'title' => $article->title,
            'excerpt' => $article->excerpt,
            'author' => $article->author,
            'image' => $getImageUrl($article->image),
            'videoUrl' => $article->video_url,
            'date' => $article->date->format('M d, Y'),
            'category' => $article->category,
            'tags' => $article->tags->pluck('tag')->toArray(),
            'isTopNews' => $article->is_top_news,
            'content' => $article->blocks->map(function ($block) {
                $formatted = [
                    'type' => $block->type,
                ];

                if ($block->type === 'text' || $block->type === 'quote') {
                    $formatted['value'] = $block->value;
                    if ($block->type === 'quote' && $block->author) {
                        $formatted['author'] = $block->author;
                    }
                } elseif ($block->type === 'image') {
                    $formatted['value'] = $block->value;
                    if ($block->caption) {
                        $formatted['caption'] = $block->caption;
                    }
                } elseif ($block->type === 'gallery') {
                    $formatted['images'] = $block->gallery_images ?? [];
                }

                return $formatted;
            })->toArray(),
        ];
    }
}
