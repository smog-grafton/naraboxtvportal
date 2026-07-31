<?php

namespace App\Http\Controllers\Api\Creator;

use App\Models\CreatorContentSubmission;
use App\Models\CreatorEarning;
use App\Models\Episode;
use App\Models\Movie;
use App\Models\PlaybackSession;
use App\Models\TVShow;
use App\Models\VideoSource;
use App\Services\CreatorEarningsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreatorDashboardController extends CreatorBaseController
{
    public function __construct(private readonly CreatorEarningsService $earnings)
    {
    }

    public function overview(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $this->creatorAccessAllowed($user)) {
            return $this->notCreator();
        }

        $movieQuery = $this->creatorMovieQuery($user);
        $tvQuery = $this->creatorTvShowQuery($user);
        $movieIds = (clone $movieQuery)->pluck('id');
        $tvIds = (clone $tvQuery)->pluck('id');
        $episodeIds = Episode::whereIn('season_id', function ($query) use ($tvIds) {
            $query->select('id')->from('seasons')->whereIn('tv_show_id', $tvIds);
        })->pluck('id');

        $contentStates = function ($query): array {
            $total = (clone $query)->count();

            return [
                'total' => $total,
                'draft' => (clone $query)->where('publication_status', 'draft')->count(),
                'processing' => (clone $query)->whereIn('processing_status', ['uploading', 'queued', 'processing'])->count(),
                'processing_failed' => (clone $query)->where('processing_status', 'processing_failed')->count(),
                'in_review' => (clone $query)->whereIn('editorial_status', ['submitted_for_review', 'under_review'])->count(),
                'changes_requested' => (clone $query)->where('editorial_status', 'changes_requested')->count(),
                'published' => (clone $query)->where('publication_status', 'published')->count(),
                'rejected' => (clone $query)->where('editorial_status', 'rejected')->count(),
            ];
        };

        $submissions = CreatorContentSubmission::where('user_id', $user->id);
        $sources = VideoSource::query()->where(function ($query) use ($movieIds, $episodeIds) {
            $query->where(function ($movieSources) use ($movieIds) {
                $movieSources->where('sourceable_type', Movie::class)->whereIn('sourceable_id', $movieIds);
            })->orWhere(function ($episodeSources) use ($episodeIds) {
                $episodeSources->where('sourceable_type', Episode::class)->whereIn('sourceable_id', $episodeIds);
            });
        });

        $recent = collect()
            ->merge((clone $movieQuery)->latest('updated_at')->limit(6)->get()->map(
                fn (Movie $movie) => $this->activityItem($movie, 'movie')
            ))
            ->merge((clone $tvQuery)->latest('updated_at')->limit(6)->get()->map(
                fn (TVShow $show) => $this->activityItem($show, 'tv_show')
            ))
            ->sortByDesc('updated_at')
            ->values()
            ->take(10);

        $profile = $this->profile($user);
        $application = $user->creatorApplication;
        $permission = $user->creatorPermission;
        $balance = $this->earnings->getBalance($user);

        $playback = PlaybackSession::where(function ($query) use ($movieIds, $tvIds) {
            $query->where(function ($movies) use ($movieIds) {
                $movies->where('media_type', 'MOVIE')->whereIn('media_id', $movieIds);
            })->orWhere(function ($shows) use ($tvIds) {
                $shows->where('media_type', 'TV_SHOW')->whereIn('media_id', $tvIds);
            });
        });
        $qualifiedSeconds = (clone $playback)
            ->where('total_watch_seconds', '>=', (int) (\App\Models\FinancialSetting::current()?->qualified_watch_seconds ?? 300))
            ->sum('total_watch_seconds');

        return response()->json([
            'success' => true,
            'data' => [
                'creator_profile' => $profile,
                'application' => $application ? [
                    'status' => $application->status,
                    'verification_status' => $application->verification_status,
                    'application_mode' => $application->application_mode,
                    'submitted_at' => $application->submitted_at?->toIso8601String(),
                    'rejection_reason' => $application->rejection_reason,
                    'open_information_requests' => $application->informationRequests()->where('status', 'open')->count(),
                ] : null,
                'permissions' => $permission ? [
                    'identity_verified' => $permission->identity_verified,
                    'draft_submission_enabled' => $permission->draft_submission_enabled,
                    'publishing_enabled' => $permission->publishing_enabled,
                    'monetization_enabled' => $permission->monetization_enabled,
                    'withdrawals_enabled' => $permission->withdrawals_enabled,
                    'is_suspended' => $permission->is_suspended,
                    'restriction_reason' => $permission->restriction_reason,
                ] : [
                    'identity_verified' => false,
                    'draft_submission_enabled' => true,
                    'publishing_enabled' => false,
                    'monetization_enabled' => false,
                    'withdrawals_enabled' => false,
                    'is_suspended' => false,
                ],
                'stats' => [
                    'movies' => $contentStates(clone $movieQuery),
                    'tv_shows' => $contentStates(clone $tvQuery),
                    'episodes' => $contentStates(Episode::whereIn('id', $episodeIds)),
                    'sources' => [
                        'total' => (clone $sources)->count(),
                        'ready' => (clone $sources)->where('is_active', true)->count(),
                        'processing' => (clone $submissions)->whereIn('processing_status', ['uploading', 'queued', 'processing'])->count(),
                        'failed' => (clone $submissions)->where('processing_status', 'processing_failed')->count(),
                    ],
                ],
                'finance' => [
                    'wallet' => $balance,
                    'rent_transactions' => CreatorEarning::forUser($user->id)
                        ->whereHas('transaction', fn ($query) => $query->where('type', 'RENT'))->count(),
                    'purchase_transactions' => CreatorEarning::forUser($user->id)
                        ->whereHas('transaction', fn ($query) => $query->where('type', 'BUY'))->count(),
                ],
                'analytics' => [
                    'plays' => (clone $playback)->count(),
                    'qualified_watch_seconds' => (int) $qualifiedSeconds,
                    'qualified_watch_minutes' => intdiv((int) $qualifiedSeconds, 60),
                    'unique_viewers' => (clone $playback)->whereNotNull('user_id')->distinct('user_id')->count('user_id'),
                ],
                'recent_activity' => $recent,
                'recent_processing' => (clone $submissions)->latest('updated_at')->limit(8)->get()->map(fn ($submission) => [
                    'id' => $submission->id,
                    'content_type' => class_basename($submission->content_type),
                    'content_id' => $submission->content_id,
                    'processing_status' => $submission->processing_status,
                    'processing_stage' => $submission->processing_stage,
                    'progress_percent' => $submission->progress_percent,
                    'status_message' => $submission->status_message,
                    'failure_reason' => $submission->failure_reason,
                    'result_metadata' => $submission->result_metadata,
                    'retry_count' => $submission->retry_count,
                    'updated_at' => $submission->updated_at?->toIso8601String(),
                ]),
                'next_steps' => $this->nextSteps($application, $permission, $profile, (clone $movieQuery)->count() + (clone $tvQuery)->count()),
            ],
        ]);
    }

    private function activityItem(Movie|TVShow $content, string $type): array
    {
        return [
            'type' => $type,
            'id' => $content->id,
            'title' => $content->title,
            'thumbnail' => $this->resolveMediaUrl($content->thumbnail),
            'processing_status' => $content->processing_status,
            'editorial_status' => $content->editorial_status,
            'publication_status' => $content->publication_status,
            'updated_at' => $content->updated_at?->toIso8601String(),
        ];
    }

    private function profile($user): ?array
    {
        $profile = $this->resolveVjProfile($user) ?: $this->resolveMediaLibraryProfile($user);
        if (! $profile) {
            return null;
        }

        return [
            'type' => $profile instanceof \App\Models\VJ ? 'vj' : 'media_library',
            'id' => $profile->id,
            'name' => $profile->name,
            'image' => $this->resolveMediaUrl($profile->image),
            'slug' => $profile->slug,
            'verified' => (bool) $profile->is_verified,
        ];
    }

    private function nextSteps($application, $permission, ?array $profile, int $contentCount): array
    {
        return [
            ['key' => 'application', 'label' => 'Complete creator application', 'done' => (bool) $application],
            ['key' => 'evidence', 'label' => 'Submit ownership evidence', 'done' => in_array($application?->verification_status, ['evidence_submitted', 'verified'], true)],
            ['key' => 'identity', 'label' => 'Administrator identity approval', 'done' => (bool) $permission?->identity_verified],
            ['key' => 'profile', 'label' => 'Complete public creator profile', 'done' => (bool) $profile],
            ['key' => 'payout', 'label' => 'Add a verified payout method', 'done' => (bool) $application?->user?->creatorPayoutMethods?->contains('is_verified', true)],
            ['key' => 'content', 'label' => 'Create your first title', 'done' => $contentCount > 0],
        ];
    }
}
