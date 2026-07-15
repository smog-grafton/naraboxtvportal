<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Movie;
use App\Models\TVShow;
use App\Models\VJ;
use App\Models\Article;
use App\Support\EditorialArticlePresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * @group Search
 *
 * Global search. Query: q. Returns archives (movies/TV), people (VJs), intel (articles).
 */
class SearchController extends Controller
{
    public function __construct(private readonly EditorialArticlePresenter $presenter)
    {
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }

    /**
     * “warmachine” matches “War Machine”; also keeps normal substring search.
     *
     * @param  callable(Builder, string, ?string): void  $textFields  ($q, $likeRaw, $likeCompact) adds title/description (and compact) clauses
     */
    private function applyBroadTextFilter(Builder $builder, string $query, callable $textFields): void
    {
        $raw = trim($query);
        if ($raw === '') {
            return;
        }

        $likeRaw = '%'.$this->escapeLike($raw).'%';

        $compact = mb_strtolower(preg_replace('/[\s\-\x{00A0}_\.]+/u', '', $raw), 'UTF-8');
        $compact = preg_replace('/[^\p{L}\p{N}]/u', '', $compact ?? '') ?? '';
        $likeCompact = (mb_strlen($compact, 'UTF-8') >= 2) ? '%'.$this->escapeLike($compact).'%' : null;

        $tokens = array_values(array_filter(
            preg_split('/\s+/u', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [],
            static fn ($t) => mb_strlen((string) $t, 'UTF-8') >= 2
        ));

        $builder->where(function (Builder $outer) use ($likeRaw, $likeCompact, $tokens, $textFields) {
            $outer->where(function (Builder $w) use ($likeRaw, $likeCompact, $textFields) {
                $textFields($w, $likeRaw, $likeCompact);
            });

            if (count($tokens) >= 2) {
                $outer->orWhere(function (Builder $andTokens) use ($tokens, $textFields) {
                    foreach ($tokens as $tok) {
                        $lt = '%'.$this->escapeLike((string) $tok).'%';
                        $andTokens->where(function (Builder $one) use ($lt, $textFields) {
                            $textFields($one, $lt, null);
                        });
                    }
                });
            }
        });
    }

    /**
     * Search. Query: q (required). Response: data.archives, data.people, data.intel.
     */
    public function search(Request $request)
    {
        $query = $request->get('q', '');

        if (empty($query)) {
            return response()->json([
                'data' => [
                    'archives' => [],
                    'people' => [],
                    'intel' => [],
                ],
            ]);
        }

        $getImageUrl = function ($path) {
            if (empty($path)) {
                return null;
            }
            if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                return $path;
            }

            return asset('storage/'.$path);
        };

        $normTitleSql = "REPLACE(REPLACE(REPLACE(LOWER(COALESCE(title,'')), ' ', ''), '-', ''), '_', '')";
        $normDescSql = "REPLACE(REPLACE(REPLACE(LOWER(COALESCE(description,'')), ' ', ''), '-', ''), '_', '')";

        $movieArchives = Movie::where('is_active', true)
            ->where(function (Builder $q) use ($query, $normTitleSql, $normDescSql) {
                $this->applyBroadTextFilter($q, $query, function (Builder $w, string $likeRaw, ?string $likeCompact) use ($normTitleSql, $normDescSql) {
                    $w->where('title', 'like', $likeRaw)
                        ->orWhere('description', 'like', $likeRaw);
                    if ($likeCompact !== null) {
                        $w->orWhereRaw("{$normTitleSql} LIKE ?", [$likeCompact])
                            ->orWhereRaw("{$normDescSql} LIKE ?", [$likeCompact]);
                    }
                });
                $likeRaw = '%'.$this->escapeLike(trim($query)).'%';
                $q->orWhereHas('genres', function (Builder $gq) use ($likeRaw) {
                    $gq->where('name', 'like', $likeRaw);
                });
                $q->orWhereHas('vj', function (Builder $vq) use ($likeRaw) {
                    $vq->where('name', 'like', $likeRaw);
                });
            })
            ->with(['genres', 'vj', 'category'])
            ->limit(35)
            ->get()
            ->unique('id')
            ->values()
            ->map(function ($movie) use ($getImageUrl) {
                return [
                    'id' => (string) $movie->id,
                    'slug' => $movie->slug,
                    'title' => $movie->title,
                    'description' => $movie->description,
                    'thumbnail' => $getImageUrl($movie->thumbnail),
                    'backdrop' => $getImageUrl($movie->backdrop),
                    'rating' => (float) $movie->rating,
                    'releaseDate' => $movie->release_date?->format('Y-m-d'),
                    'category' => $movie->category->name ?? '',
                    'mediaType' => $movie->media_type,
                    'media_type' => $movie->media_type === 'SERIES' ? 'TV_SHOW' : $movie->media_type,
                    'vj' => $movie->vj ? $movie->vj->name : null,
                    'genre' => $movie->genres->pluck('name')->toArray(),
                    'trendingScore' => $movie->trending_score,
                    'accessType' => $movie->access_type,
                    'videoUrl' => $movie->video_url,
                    'duration' => $movie->duration,
                ];
            });

        $tvArchives = TVShow::where('is_active', true)
            ->where(function (Builder $q) use ($query, $normTitleSql, $normDescSql) {
                $this->applyBroadTextFilter($q, $query, function (Builder $w, string $likeRaw, ?string $likeCompact) use ($normTitleSql, $normDescSql) {
                    $w->where('title', 'like', $likeRaw)
                        ->orWhere('description', 'like', $likeRaw);
                    if ($likeCompact !== null) {
                        $w->orWhereRaw("{$normTitleSql} LIKE ?", [$likeCompact])
                            ->orWhereRaw("{$normDescSql} LIKE ?", [$likeCompact]);
                    }
                });
                $likeRaw = '%'.$this->escapeLike(trim($query)).'%';
                $q->orWhereHas('genres', function (Builder $gq) use ($likeRaw) {
                    $gq->where('name', 'like', $likeRaw);
                });
                $q->orWhereHas('vj', function (Builder $vq) use ($likeRaw) {
                    $vq->where('name', 'like', $likeRaw);
                });
            })
            ->with(['genres', 'vj', 'category'])
            ->limit(35)
            ->get()
            ->unique('id')
            ->values()
            ->map(function ($show) use ($getImageUrl) {
                return [
                    'id' => (string) $show->id,
                    'slug' => $show->slug,
                    'title' => $show->title,
                    'description' => $show->description,
                    'thumbnail' => $getImageUrl($show->thumbnail),
                    'backdrop' => $getImageUrl($show->backdrop),
                    'rating' => (float) $show->rating,
                    'releaseDate' => $show->release_date?->format('Y-m-d'),
                    'category' => $show->category->name ?? '',
                    'mediaType' => 'TV_SHOW',
                    'media_type' => 'TV_SHOW',
                    'vj' => $show->vj ? $show->vj->name : null,
                    'genre' => $show->genres->pluck('name')->toArray(),
                    'trendingScore' => $show->trending_score,
                    'accessType' => $show->access_type,
                    'videoUrl' => null,
                    'duration' => $show->duration,
                ];
            });

        $archives = $movieArchives
            ->concat($tvArchives)
            ->sortByDesc(fn (array $item) => [
                (float) ($item['trendingScore'] ?? 0),
                (float) ($item['rating'] ?? 0),
            ])
            ->take(40)
            ->values();

        $normVjName = "REPLACE(REPLACE(REPLACE(LOWER(COALESCE(name,'')), ' ', ''), '-', ''), '_', '')";
        $normVjBio = "REPLACE(REPLACE(REPLACE(LOWER(COALESCE(bio,'')), ' ', ''), '-', ''), '_', '')";

        $people = VJ::where('is_active', true)
            ->where(function (Builder $q) use ($query, $normVjName, $normVjBio) {
                $this->applyBroadTextFilter($q, $query, function (Builder $w, string $likeRaw, ?string $likeCompact) use ($normVjName, $normVjBio) {
                    $w->where('name', 'like', $likeRaw)
                        ->orWhere('bio', 'like', $likeRaw);
                    if ($likeCompact !== null) {
                        $w->orWhereRaw("{$normVjName} LIKE ?", [$likeCompact])
                            ->orWhereRaw("{$normVjBio} LIKE ?", [$likeCompact]);
                    }
                });
                $likeRaw = '%'.$this->escapeLike(trim($query)).'%';
                $q->orWhereHas('genres', function (Builder $gq) use ($likeRaw) {
                    $gq->where('name', 'like', $likeRaw);
                });
            })
            ->with('genres')
            ->limit(18)
            ->get()
            ->unique('id')
            ->values()
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

        $normArtTitle = "REPLACE(REPLACE(REPLACE(LOWER(COALESCE(title,'')), ' ', ''), '-', ''), '_', '')";
        $normArtExcerpt = "REPLACE(REPLACE(REPLACE(LOWER(COALESCE(excerpt,'')), ' ', ''), '-', ''), '_', '')";

        $intel = Article::where('is_published', true)
            ->where(function (Builder $q) use ($query, $normArtTitle, $normArtExcerpt) {
                $this->applyBroadTextFilter($q, $query, function (Builder $w, string $likeRaw, ?string $likeCompact) use ($normArtTitle, $normArtExcerpt) {
                    $w->where('title', 'like', $likeRaw)
                        ->orWhere('excerpt', 'like', $likeRaw)
                        ->orWhere('category', 'like', $likeRaw);
                    if ($likeCompact !== null) {
                        $w->orWhereRaw("{$normArtTitle} LIKE ?", [$likeCompact])
                            ->orWhereRaw("{$normArtExcerpt} LIKE ?", [$likeCompact]);
                    }
                });
            })
            ->with(['primaryCategory', 'authorUser', 'tags'])
            ->limit(18)
            ->get()
            ->map(fn (Article $article) => $this->presenter->summary($article));

        return response()->json([
            'data' => [
                'archives' => $archives->all(),
                'people' => $people->all(),
                'intel' => $intel->values()->all(),
            ],
        ]);
    }
}
