<?php

namespace App\Services;

use App\Models\Movie;
use App\Models\TVShow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Scores a bounded candidate pool in-memory (no per-row queries) using a fixed
 * signal priority: shared genres >> same VJ >> language/category/country >>
 * shared cast >> a tiny popularity tie-break. Movies/TV shows recommending
 * "the wrong content type" is prevented structurally by which pools are
 * queried (see forMovie/forTvShow), not by a scoring bonus.
 *
 * Legacy quirk: a `movies` row can have media_type=SERIES (content authored
 * before the dedicated tv_shows table existed). Its true peer group is the
 * whole "show" universe, so both directions union that legacy pool with the
 * dedicated TVShow table.
 */
class SimilarTitlesService
{
    private const CANDIDATE_POOL_LIMIT = 150;
    private const MIN_LIMIT = 1;
    private const MAX_LIMIT = 20;
    private const DEFAULT_LIMIT = 10;

    private const WEIGHT_PER_GENRE = 10.0;
    private const WEIGHT_VJ = 4.0;
    private const WEIGHT_LANGUAGE = 1.5;
    private const WEIGHT_CATEGORY = 1.5;
    private const WEIGHT_COUNTRY = 1.0;
    private const WEIGHT_PER_CAST = 0.6;
    private const MAX_CAST_SCORED = 3;
    private const POPULARITY_TIEBREAK_FACTOR = 0.01;

    public function forMovie(Movie $movie, int $limit = self::DEFAULT_LIMIT): Collection
    {
        $isLegacySeries = $movie->media_type === 'SERIES';

        $poolBuilders = $isLegacySeries
            ? [
                fn (array $genreIds, bool $requireGenre): Builder => $this->baseQuery(
                    Movie::query()->where('media_type', 'SERIES')->where('id', '!=', $movie->id),
                    $genreIds,
                    $requireGenre
                ),
                fn (array $genreIds, bool $requireGenre): Builder => $this->baseQuery(
                    TVShow::query(),
                    $genreIds,
                    $requireGenre
                ),
            ]
            : [
                fn (array $genreIds, bool $requireGenre): Builder => $this->baseQuery(
                    Movie::query()->where('media_type', 'MOVIE')->where('id', '!=', $movie->id),
                    $genreIds,
                    $requireGenre
                ),
            ];

        return $this->rank($movie, $poolBuilders, $limit);
    }

    public function forTvShow(TVShow $show, int $limit = self::DEFAULT_LIMIT): Collection
    {
        $poolBuilders = [
            fn (array $genreIds, bool $requireGenre): Builder => $this->baseQuery(
                TVShow::query()->where('id', '!=', $show->id),
                $genreIds,
                $requireGenre
            ),
            fn (array $genreIds, bool $requireGenre): Builder => $this->baseQuery(
                Movie::query()->where('media_type', 'SERIES'),
                $genreIds,
                $requireGenre
            ),
        ];

        return $this->rank($show, $poolBuilders, $limit);
    }

    /**
     * @param array<int, callable(array<int,int>, bool): Builder> $poolBuilders
     */
    private function rank(Model $source, array $poolBuilders, int $limit): Collection
    {
        $limit = max(self::MIN_LIMIT, min(self::MAX_LIMIT, $limit));

        $source->loadMissing(['genres', 'actors']);
        $genreIds = $source->genres->pluck('id')->all();
        $actorIds = $source->actors->pluck('id')->all();

        $primary = $this->fetchAndScore($poolBuilders, $genreIds, $actorIds, $source, requireGenre: !empty($genreIds));

        $selected = $primary->sortByDesc('similarity_score')->take($limit)->values();

        // Fallback: only top up missing slots with trending titles, never blended into the ranked list.
        if ($selected->count() < $limit) {
            $seen = $selected->map(fn (Model $m): string => get_class($m) . ':' . $m->getKey())->all();
            $need = $limit - $selected->count();

            $fallback = $this->fetchAndScore($poolBuilders, [], $actorIds, $source, requireGenre: false)
                ->reject(fn (Model $m): bool => in_array(get_class($m) . ':' . $m->getKey(), $seen, true))
                ->sortByDesc(fn (Model $m): int => (int) ($m->views_count ?? 0) + (int) ($m->manual_views ?? 0))
                ->take($need)
                ->values();

            $selected = $selected->concat($fallback)->values();
        }

        return $selected;
    }

    private function baseQuery(Builder $query, array $genreIds, bool $requireGenre): Builder
    {
        $query->where('is_active', true)
            ->publiclyVisible()
            ->with(['genres', 'actors']);

        if ($requireGenre && !empty($genreIds)) {
            $query->whereHas('genres', fn (Builder $q) => $q->whereIn('genres.id', $genreIds));
        }

        return $query->orderByRaw('(views_count + manual_views) DESC')->limit(self::CANDIDATE_POOL_LIMIT);
    }

    /**
     * @param array<int, callable(array<int,int>, bool): Builder> $poolBuilders
     * @param array<int,int> $genreIds
     * @param array<int,int> $actorIds
     */
    private function fetchAndScore(array $poolBuilders, array $genreIds, array $actorIds, Model $source, bool $requireGenre): Collection
    {
        $pool = collect();
        foreach ($poolBuilders as $build) {
            $pool = $pool->concat($build($genreIds, $requireGenre)->get());
        }

        return $pool->map(function (Model $candidate) use ($source, $genreIds, $actorIds) {
            $candidate->similarity_score = $this->score($candidate, $source, $genreIds, $actorIds);
            return $candidate;
        });
    }

    /**
     * @param array<int,int> $genreIds
     * @param array<int,int> $actorIds
     */
    private function score(Model $candidate, Model $source, array $genreIds, array $actorIds): float
    {
        $score = 0.0;

        $sharedGenres = $candidate->genres->pluck('id')->intersect($genreIds)->count();
        $score += $sharedGenres * self::WEIGHT_PER_GENRE;

        if ($source->vj_id && $candidate->vj_id === $source->vj_id) {
            $score += self::WEIGHT_VJ;
        }

        if ($source->language && $candidate->language === $source->language) {
            $score += self::WEIGHT_LANGUAGE;
        }

        if ($source->category_id && $candidate->category_id === $source->category_id) {
            $score += self::WEIGHT_CATEGORY;
        }

        if ($source->country && $candidate->country === $source->country) {
            $score += self::WEIGHT_COUNTRY;
        }

        $sharedCast = $candidate->actors->pluck('id')->intersect($actorIds)->take(self::MAX_CAST_SCORED)->count();
        $score += $sharedCast * self::WEIGHT_PER_CAST;

        $views = (int) ($candidate->views_count ?? 0) + (int) ($candidate->manual_views ?? 0);
        $score += log($views + 1) * self::POPULARITY_TIEBREAK_FACTOR;

        return $score;
    }
}
