<?php

namespace App\Services;

use App\Models\Movie;
use App\Models\PlaybackSession;
use App\Models\TVShow;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class TrendingService
{
    public const PERIODS = ['now', 'today', 'week', 'month', 'all_time'];

    private const MINIMUM_WATCH_SECONDS = 30;

    private const MAX_SESSION_SCORE_SECONDS = 7200;

    /**
     * @return array{items: Collection<int, Model>, meta: array<string, mixed>}
     */
    public function get(string $type, string $period, ?int $vjId = null, int $limit = 12): array
    {
        $timezone = (string) config('app.trending_timezone', 'Africa/Kampala');
        $now = CarbonImmutable::now($timezone);
        $windowStart = $this->windowStart($period, $now)?->utc();
        $windowEnd = $now->utc();
        $ttl = $this->cacheTtl($period);
        $cacheBucket = intdiv($windowEnd->getTimestamp(), $ttl);
        $cacheKey = implode(':', [
            'trending',
            'v1',
            $type,
            $period,
            $vjId ?? 'all',
            $limit,
            $cacheBucket,
        ]);

        /** @var Collection<int, Model> $items */
        $items = Cache::remember($cacheKey, $ttl, function () use ($type, $vjId, $limit, $windowStart, $windowEnd) {
            $model = $type === 'movie' ? new Movie : new TVShow;
            $table = $model->getTable();
            $mediaType = $type === 'movie' ? 'MOVIE' : 'TV_SHOW';
            $effectiveWatchSeconds = 'CASE WHEN COALESCE(total_watch_seconds, 0) >= COALESCE(max_position_seconds, 0) THEN COALESCE(total_watch_seconds, 0) ELSE COALESCE(max_position_seconds, 0) END';

            $engagement = PlaybackSession::query()
                ->selectRaw('media_id, COUNT(*) AS trending_session_count')
                ->selectRaw("SUM(CASE WHEN ({$effectiveWatchSeconds}) > ? THEN ? ELSE ({$effectiveWatchSeconds}) END) AS trending_watch_seconds", [
                    self::MAX_SESSION_SCORE_SECONDS,
                    self::MAX_SESSION_SCORE_SECONDS,
                ])
                ->where('media_type', $mediaType)
                ->whereNotNull('started_at')
                ->where(function ($query): void {
                    $query->where('total_watch_seconds', '>=', self::MINIMUM_WATCH_SECONDS)
                        ->orWhere('max_position_seconds', '>=', self::MINIMUM_WATCH_SECONDS);
                })
                ->when($windowStart, fn ($query) => $query->where('started_at', '>=', $windowStart))
                ->where('started_at', '<=', $windowEnd)
                ->groupBy('media_id');

            $query = $model->newQuery()
                ->where("{$table}.is_active", true)
                ->where("{$table}.content_status", 'published')
                ->when($type === 'movie', fn ($query) => $query->where("{$table}.media_type", 'MOVIE'))
                ->when($vjId, fn ($query) => $query->where("{$table}.vj_id", $vjId))
                ->leftJoinSub($engagement, 'trending_engagement', function ($join) use ($table): void {
                    $join->on("{$table}.id", '=', 'trending_engagement.media_id');
                })
                ->select("{$table}.*")
                ->selectRaw('COALESCE(trending_engagement.trending_session_count, 0) AS trending_session_count')
                ->selectRaw('COALESCE(trending_engagement.trending_watch_seconds, 0) AS trending_watch_seconds')
                ->selectRaw('(COALESCE(trending_engagement.trending_session_count, 0) * 100) + (COALESCE(trending_engagement.trending_watch_seconds, 0) / 60.0) AS period_trending_score')
                ->with(['genres', 'vj', 'mediaLibrary', 'category'])
                ->orderByRaw('CASE WHEN trending_engagement.media_id IS NULL THEN 0 ELSE 1 END DESC')
                ->orderByDesc('period_trending_score')
                ->orderByRaw("({$table}.views_count + {$table}.manual_views) DESC")
                ->orderByRaw("COALESCE({$table}.published_at, {$table}.created_at) DESC")
                ->limit($limit);

            return $query->get();
        });

        return [
            'items' => $items,
            'meta' => [
                'type' => $type,
                'period' => $period,
                'label' => $this->periodLabel($period),
                'vj_id' => $vjId,
                'timezone' => $timezone,
                'window_start' => $windowStart?->toIso8601String(),
                'window_end' => $windowEnd->toIso8601String(),
                'minimum_watch_seconds' => self::MINIMUM_WATCH_SECONDS,
                'cache_ttl_seconds' => $ttl,
            ],
        ];
    }

    private function windowStart(string $period, CarbonImmutable $now): ?CarbonImmutable
    {
        return match ($period) {
            'now' => $now->subHours(6),
            'today' => $now->startOfDay(),
            'week' => $now->startOfWeek(),
            'month' => $now->startOfMonth(),
            'all_time' => null,
        };
    }

    private function cacheTtl(string $period): int
    {
        return match ($period) {
            'now' => 60,
            'today' => 120,
            'week', 'month' => 300,
            'all_time' => 600,
        };
    }

    private function periodLabel(string $period): string
    {
        return match ($period) {
            'now' => 'Trending Now',
            'today' => 'Today',
            'week' => 'This Week',
            'month' => 'This Month',
            'all_time' => 'All Time',
        };
    }
}
