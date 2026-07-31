<?php

namespace App\Http\Controllers\Api\Creator;

use App\Models\CreatorEarning;
use App\Models\FinancialSetting;
use App\Models\Movie;
use App\Models\PlaybackSession;
use App\Models\TVShow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CreatorAnalyticsController extends CreatorBaseController
{
    public function __invoke(Request $request): JsonResponse
    {
        if (! $this->creatorAccessAllowed($request->user())) {
            return $this->notCreator();
        }

        $days = max(7, min(365, (int) $request->integer('days', 30)));
        $from = now()->subDays($days - 1)->startOfDay();
        $movieIds = $this->creatorMovieQuery($request->user())->pluck('id');
        $showIds = $this->creatorTvShowQuery($request->user())->pluck('id');
        $sessions = PlaybackSession::query()
            ->where('started_at', '>=', $from)
            ->where(function ($query) use ($movieIds, $showIds) {
                $query->where(fn ($movie) => $movie->where('media_type', 'MOVIE')->whereIn('media_id', $movieIds))
                    ->orWhere(fn ($show) => $show->where('media_type', 'TV_SHOW')->whereIn('media_id', $showIds));
            });
        $threshold = (int) (FinancialSetting::current()?->qualified_watch_seconds ?? 300);

        $daily = (clone $sessions)
            ->selectRaw('DATE(started_at) as day, COUNT(*) as plays, SUM(total_watch_seconds) as watch_seconds')
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy('day');
        $timeline = collect(range(0, $days - 1))->map(function (int $offset) use ($from, $daily) {
            $day = $from->copy()->addDays($offset)->toDateString();
            $row = $daily->get($day);

            return [
                'date' => $day,
                'plays' => (int) ($row?->plays ?? 0),
                'watch_minutes' => intdiv((int) ($row?->watch_seconds ?? 0), 60),
            ];
        });

        $top = (clone $sessions)
            ->selectRaw('media_type, media_id, COUNT(*) as plays, SUM(total_watch_seconds) as watch_seconds')
            ->groupBy('media_type', 'media_id')
            ->orderByDesc('watch_seconds')
            ->limit(10)
            ->get()
            ->map(function ($row) {
                $content = strtoupper($row->media_type) === 'TV_SHOW'
                    ? TVShow::find($row->media_id)
                    : Movie::find($row->media_id);

                return [
                    'type' => strtolower($row->media_type),
                    'id' => (int) $row->media_id,
                    'title' => $content?->title ?? 'Removed title',
                    'plays' => (int) $row->plays,
                    'watch_minutes' => intdiv((int) $row->watch_seconds, 60),
                ];
            });

        $earnings = CreatorEarning::where('user_id', $request->user()->id)
            ->where('created_at', '>=', $from)
            ->selectRaw('DATE(created_at) as day, SUM(creator_amount_minor) as amount_minor')
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'period_days' => $days,
                'summary' => [
                    'plays' => (clone $sessions)->count(),
                    'qualified_plays' => (clone $sessions)->where('total_watch_seconds', '>=', $threshold)->count(),
                    'watch_minutes' => intdiv((int) (clone $sessions)->sum('total_watch_seconds'), 60),
                    'unique_viewers' => (clone $sessions)->whereNotNull('user_id')->distinct()->count('user_id'),
                    'finalized_earnings_minor' => (int) CreatorEarning::where('user_id', $request->user()->id)
                        ->where('created_at', '>=', $from)
                        ->whereIn('status', ['available', 'withdrawn'])
                        ->sum('creator_amount_minor'),
                    'estimated_earnings_minor' => null,
                ],
                'timeline' => $timeline,
                'top_titles' => $top,
                'earnings_timeline' => $earnings->map(fn ($row) => [
                    'date' => $row->day,
                    'amount_minor' => (int) $row->amount_minor,
                ]),
                'definitions' => [
                    'views' => 'Playback sessions started on your titles.',
                    'qualified_plays' => "Playback sessions reaching at least {$threshold} seconds; settlement fraud and subscription checks still apply.",
                    'available' => 'Finalized money currently eligible for withdrawal.',
                ],
            ],
        ]);
    }
}
