<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Movie;
use App\Models\TVShow;
use App\Services\TrendingService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TrendingController extends Controller
{
    public function __invoke(Request $request, TrendingService $trendingService)
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['movie', 'tv_show'])],
            'period' => ['sometimes', Rule::in(TrendingService::PERIODS)],
            'vj_id' => [
                'nullable',
                'integer',
                Rule::exists('vjs', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:30'],
        ]);

        $type = $validated['type'];
        $period = $validated['period'] ?? 'now';
        $vjId = isset($validated['vj_id']) ? (int) $validated['vj_id'] : null;
        $limit = (int) ($validated['limit'] ?? 12);
        $result = $trendingService->get($type, $period, $vjId, $limit);
        $movieController = app(MovieController::class);
        $tvShowController = app(TVShowController::class);

        $data = $result['items']->map(function (Movie|TVShow $item) use ($movieController, $tvShowController): array {
            $payload = $item instanceof Movie
                ? $movieController->formatMovieSummary($item)
                : $tvShowController->formatTVShowSummary($item);

            $payload['trending'] = [
                'score' => round((float) ($item->period_trending_score ?? 0), 2),
                'sessions' => (int) ($item->trending_session_count ?? 0),
                'watch_seconds' => (int) ($item->trending_watch_seconds ?? 0),
            ];

            return $payload;
        })->values();

        return response()
            ->json(['data' => $data, 'meta' => $result['meta']])
            ->header('Cache-Control', 'public, max-age=30, stale-while-revalidate=120');
    }
}
