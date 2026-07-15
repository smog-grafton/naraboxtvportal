<?php

namespace App\Http\Controllers\Api\Creator;

use App\Models\Movie;
use App\Models\TVShow;
use App\Models\VideoSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreatorDashboardController extends CreatorBaseController
{
    public function overview(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->isCreator() && !$user->isAdmin()) {
            return $this->notCreator();
        }

        $movieQuery = $this->creatorMovieQuery($user);
        $tvQuery = $this->creatorTvShowQuery($user);

        // Movie stats
        $totalMovies = (clone $movieQuery)->count();
        $draftMovies = (clone $movieQuery)->where('publish_status', 'draft')->count();
        $pendingMovies = (clone $movieQuery)->where('publish_status', 'pending_review')->count();
        $publishedMovies = (clone $movieQuery)->where('publish_status', 'published')->count();
        $rejectedMovies = (clone $movieQuery)->where('publish_status', 'rejected')->count();

        // TV show stats
        $totalTvShows = (clone $tvQuery)->count();
        $draftTvShows = (clone $tvQuery)->where('publish_status', 'draft')->count();
        $publishedTvShows = (clone $tvQuery)->where('publish_status', 'published')->count();

        // Video source stats — get all movie IDs for this creator
        $movieIds = (clone $movieQuery)->pluck('id');
        $sourcesQuery = VideoSource::where('sourceable_type', Movie::class)
            ->whereIn('sourceable_id', $movieIds);

        $totalSources = $sourcesQuery->count();
        $activeSources = (clone $sourcesQuery)->where('is_active', true)->count();

        // Count sources with pending CDN/Object Storage processing
        $pendingSources = (clone $sourcesQuery)
            ->where('is_active', false)
            ->where(function ($query) {
                $query->whereIn('metadata->cdn_status', ['importing', 'downloading', 'pending_upload', 'processing'])
                    ->orWhereIn('metadata->fetch_status', ['queued', 'processing', 'downloading'])
                    ->orWhereIn('metadata->telegram_status', ['telegram_submitted', 'fetching']);
            })
            ->count();

        // Recent activity — last 10 movies/shows
        $recentMovies = (clone $movieQuery)
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get(['id', 'title', 'thumbnail', 'publish_status', 'updated_at']);

        $recentTvShows = (clone $tvQuery)
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get(['id', 'title', 'thumbnail', 'publish_status', 'updated_at']);

        $recentActivity = collect()
            ->merge($recentMovies->map(fn($m) => [
                'type'           => 'movie',
                'id'             => $m->id,
                'title'          => $m->title,
                'thumbnail'      => $m->thumbnail,
                'publish_status' => $m->publish_status ?? 'draft',
                'updated_at'     => $m->updated_at?->toIso8601String(),
            ]))
            ->merge($recentTvShows->map(fn($s) => [
                'type'           => 'tv_show',
                'id'             => $s->id,
                'title'          => $s->title,
                'thumbnail'      => $s->thumbnail,
                'publish_status' => $s->publish_status ?? 'draft',
                'updated_at'     => $s->updated_at?->toIso8601String(),
            ]))
            ->sortByDesc('updated_at')
            ->values()
            ->take(10);

        // Resolve creator profile info
        $creatorProfile = null;
        if ($user->isVJ()) {
            $vj = $this->resolveVjProfile($user);
            $creatorProfile = $vj ? [
                'type'     => 'vj',
                'name'     => $vj->name,
                'image'    => $vj->image,
                'slug'     => $vj->slug,
                'verified' => (bool) ($vj->is_verified ?? false),
                'phone'    => $vj->user?->phone ?? $user->phone ?? null,
            ] : null;
        } elseif ($user->isMediaLibrary()) {
            $library = $this->resolveMediaLibraryProfile($user);
            $creatorProfile = $library ? [
                'type'     => 'media_library',
                'name'     => $library->name,
                'image'    => $library->image,
                'slug'     => $library->slug,
                'verified' => (bool) ($library->is_verified ?? false),
                'phone'    => $library->user?->phone ?? $user->phone ?? null,
            ] : null;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'creator_profile' => $creatorProfile,
                'stats' => [
                    'movies' => [
                        'total'     => $totalMovies,
                        'draft'     => $draftMovies,
                        'pending'   => $pendingMovies,
                        'published' => $publishedMovies,
                        'rejected'  => $rejectedMovies,
                    ],
                    'tv_shows' => [
                        'total'     => $totalTvShows,
                        'draft'     => $draftTvShows,
                        'published' => $publishedTvShows,
                    ],
                    'sources' => [
                        'total'   => $totalSources,
                        'active'  => $activeSources,
                        'pending' => $pendingSources,
                    ],
                ],
                'recent_activity' => $recentActivity,
                'next_steps'      => $this->buildNextSteps($totalMovies, $pendingSources, $publishedMovies, $creatorProfile),
            ],
        ]);
    }

    private function buildNextSteps(int $total, int $pendingSources, int $published, ?array $profile): array
    {
        $steps = [];

        if (!$profile) {
            $steps[] = ['key' => 'setup_profile', 'label' => 'Complete your creator profile', 'done' => false];
        }

        if ($total === 0) {
            $steps[] = ['key' => 'add_first_movie', 'label' => 'Add your first movie or TV show', 'done' => false];
        }

        if ($pendingSources > 0) {
            $steps[] = ['key' => 'pending_sources', 'label' => "$pendingSources source(s) still processing", 'done' => false];
        }

        if ($total > 0 && $published === 0) {
            $steps[] = ['key' => 'publish_content', 'label' => 'Submit your content for review to get published', 'done' => false];
        }

        if (empty($steps) && $published > 0) {
            $steps[] = ['key' => 'all_done', 'label' => 'All good! Your content is live.', 'done' => true];
        }

        return $steps;
    }
}
