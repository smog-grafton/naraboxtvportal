<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Support\EditorialArticlePresenter;
use Illuminate\Http\Request;

/**
 * @group Articles
 *
 * News/editorial. List and fetch by id or slug; filter by category, top_news.
 */
class ArticleController extends Controller
{
    public function __construct(private readonly EditorialArticlePresenter $presenter)
    {
    }

    /**
     * List articles. Query: category, top_news, per_page.
     */
    public function index(Request $request)
    {
        $query = Article::where('is_published', true)
            ->with(['primaryCategory', 'authorUser', 'tags']);

        if ($request->filled('category')) {
            $category = $request->string('category')->toString();

            $query->where(function ($builder) use ($category) {
                $builder->where('category', $category)
                    ->orWhereHas('primaryCategory', function ($categoryQuery) use ($category) {
                        $categoryQuery
                            ->where('name', $category)
                            ->orWhere('slug', $category);
                    });
            });
        }

        if ($request->filled('post_type')) {
            $query->where('post_type', $request->string('post_type')->toString());
        }

        if ($request->boolean('top_news')) {
            $query->where('is_top_news', true);
        }

        $articles = $query->orderBy('date', 'desc')
            ->orderBy('is_top_news', 'desc')
            ->paginate($request->get('per_page', 10));

        $articles->setCollection(
            $articles->getCollection()->map(fn (Article $article) => $this->presenter->summary($article))
        );

        return response()->json([
            'data' => $articles->items(),
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
            ->with([
                'blocks' => fn ($query) => $query->orderBy('order'),
                'blocks.movie',
                'blocks.tvShow',
                'blocks.vj',
                'tags',
                'primaryCategory',
                'authorUser',
                'movie',
                'tvShow',
                'vj',
            ])
            ->where(function ($query) use ($id) {
                $query->where('id', $id)
                      ->orWhere('slug', $id);
            })
            ->firstOrFail();

        return response()->json($this->presenter->detail($article));
    }
}
