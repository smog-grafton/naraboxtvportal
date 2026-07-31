<?php

namespace App\Http\Controllers\Api\Creator;

use App\Models\Episode;
use App\Models\Season;
use App\Services\TmdbService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\CreatorContentWorkflowService;
use App\Services\CreatorContentRules;
use Illuminate\Support\Facades\DB;

class CreatorSeasonController extends CreatorBaseController
{
    public function __construct(
        private readonly TmdbService $tmdb,
        private readonly CreatorContentWorkflowService $workflow,
        private readonly CreatorContentRules $rules
    ) {
    }

    /**
     * Create a season for a TV show (manual or from TMDB data).
     */
    public function store(Request $request, int $showId): JsonResponse
    {
        $user = $request->user();
        $show = $this->creatorTvShowQuery($user)->find($showId);

        if (!$show) {
            return response()->json(['success' => false, 'message' => 'TV show not found.'], 404);
        }

        $validated = $request->validate($this->rules->season());

        if (Season::where('tv_show_id', $show->id)->where('number', $validated['season_number'])->exists()) {
            return response()->json(['success' => false, 'message' => 'Season ' . $validated['season_number'] . ' already exists.'], 422);
        }

        $season = Season::create([
            'tv_show_id' => $show->id,
            'media_id' => null,
            'number' => $validated['season_number'],
            'title' => $validated['title'] ?? 'Season ' . $validated['season_number'],
            'description' => $validated['description'] ?? null,
            'air_date' => $validated['air_date'] ?? null,
        ]);

        $this->refreshTvShowCounts($show);

        return response()->json([
            'success' => true,
            'message' => 'Season created.',
            'data' => $this->formatSeason($season),
        ], 201);
    }

    /**
     * Import all seasons (and episodes) from TMDB.
     */
    public function importFromTmdb(Request $request, int $showId): JsonResponse
    {
        $user = $request->user();
        $show = $this->creatorTvShowQuery($user)->find($showId);

        if (!$show) {
            return response()->json(['success' => false, 'message' => 'TV show not found.'], 404);
        }

        if (!$show->tmdb_id) {
            return response()->json(['success' => false, 'message' => 'TV show has no TMDB ID. Add TMDB metadata first.'], 422);
        }

        $raw = $this->tmdb->getTvShowDetails($show->tmdb_id);
        if (!$raw) {
            return response()->json(['success' => false, 'message' => 'TMDB TV show not found.'], 404);
        }

        $tmdbSeasons = $raw['seasons'] ?? [];
        $created = 0;

        foreach ($tmdbSeasons as $s) {
            $seasonNum = (int) ($s['season_number'] ?? 0);
            if ($seasonNum === 0) continue;

            if (Season::where('tv_show_id', $show->id)->where('number', $seasonNum)->exists()) {
                continue;
            }

            $seasonDetails = $this->tmdb->getSeasonDetails($show->tmdb_id, $seasonNum);
            if (!$seasonDetails) continue;

            $season = Season::create([
                'tv_show_id' => $show->id,
                'media_id' => null,
                'number' => $seasonNum,
                'title' => $seasonDetails['name'] ?? 'Season ' . $seasonNum,
                'description' => $seasonDetails['overview'] ?? null,
                'air_date' => $seasonDetails['air_date'] ?? null,
            ]);

            foreach ($seasonDetails['episodes'] ?? [] as $ep) {
                $epNum = (int) ($ep['episode_number'] ?? 0);
                if ($epNum < 1) continue;

                $episode = Episode::create([
                    'season_id' => $season->id,
                    'number' => $epNum,
                    'title' => $ep['name'] ?? 'Episode ' . $epNum,
                    'description' => $ep['overview'] ?? null,
                    'duration' => isset($ep['runtime']) && $ep['runtime'] ? $ep['runtime'] . ' min' : null,
                    'thumbnail' => $this->tmdb->getImageUrl($ep['still_path'] ?? null, 'w342'),
                ]);
                $this->workflow->initialize($episode, $user, 'tmdb_import');
            }
            $created++;
        }

        $this->refreshTvShowCounts($show);

        return response()->json([
            'success' => true,
            'message' => "Imported {$created} season(s) from TMDB.",
            'data' => ['created' => $created],
        ]);
    }

    /**
     * Create an episode for a season.
     */
    public function storeEpisode(Request $request, int $seasonId): JsonResponse
    {
        $user = $request->user();
        $season = Season::with('tvShow')->find($seasonId);

        if (!$season || !$season->tvShow) {
            return response()->json(['success' => false, 'message' => 'Season not found.'], 404);
        }

        $show = $this->creatorTvShowQuery($user)->find($season->tv_show_id);
        if (!$show) {
            return response()->json(['success' => false, 'message' => 'Not authorized.'], 403);
        }

        $validated = $request->validate($this->rules->episode());

        if (Episode::where('season_id', $season->id)->where('number', $validated['episode_number'])->exists()) {
            return response()->json(['success' => false, 'message' => 'Episode ' . $validated['episode_number'] . ' already exists.'], 422);
        }

        $thumbnail = $validated['thumbnail'] ?? null;
        if ($request->hasFile('thumbnail_file')) {
            $thumbnail = $request->file('thumbnail_file')->store('episode-thumbnails', 'public');
        }
        $episode = Episode::create([
            'season_id' => $season->id,
            'number' => $validated['episode_number'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'short_description' => $validated['short_description'] ?? null,
            'duration' => $validated['duration'] ?? null,
            'release_date' => $validated['release_date'] ?? null,
            'translation_language' => $validated['translation_language'] ?? null,
            'thumbnail' => $thumbnail,
            'download_enabled' => $validated['download_enabled'] ?? true,
            'scheduled_for' => $validated['scheduled_for'] ?? null,
            'tags' => $validated['tags'] ?? null,
            'is_active' => false,
        ]);
        $this->workflow->initialize($episode, $user, $user->isAdmin() ? 'administrator' : 'creator');

        $this->refreshTvShowCounts($show);

        return response()->json([
            'success' => true,
            'message' => 'Episode created.',
            'data' => $this->formatEpisode($episode),
        ], 201);
    }

    /**
     * Update a season.
     */
    public function update(Request $request, int $seasonId): JsonResponse
    {
        $user = $request->user();
        $season = Season::with('tvShow')->find($seasonId);

        if (!$season || !$season->tvShow) {
            return response()->json(['success' => false, 'message' => 'Season not found.'], 404);
        }

        $show = $this->creatorTvShowQuery($user)->find($season->tv_show_id);
        if (!$show) {
            return response()->json(['success' => false, 'message' => 'Not authorized.'], 403);
        }

        $validated = $request->validate($this->rules->season(true));

        if (array_key_exists('season_number', $validated)) {
            $validated['number'] = $validated['season_number'];
            unset($validated['season_number']);
        }
        $season->fill($validated);
        $season->save();

        return response()->json([
            'success' => true,
            'message' => 'Season updated.',
            'data' => $this->formatSeason($season),
        ]);
    }

    /**
     * Update an episode.
     */
    public function updateEpisode(Request $request, int $episodeId): JsonResponse
    {
        $user = $request->user();
        $episode = Episode::with('season.tvShow')->find($episodeId);

        if (!$episode || !$episode->season) {
            return response()->json(['success' => false, 'message' => 'Episode not found.'], 404);
        }

        $show = $this->creatorTvShowQuery($user)->find($episode->season->tv_show_id);
        if (!$show) {
            return response()->json(['success' => false, 'message' => 'Not authorized.'], 403);
        }

        $validated = $request->validate($this->rules->episode(true));

        if (array_key_exists('episode_number', $validated)) {
            $validated['number'] = $validated['episode_number'];
            unset($validated['episode_number']);
        }
        if ($request->hasFile('thumbnail_file')) {
            $validated['thumbnail'] = $request->file('thumbnail_file')->store('episode-thumbnails', 'public');
        }
        unset($validated['thumbnail_file']);
        $episode->fill($validated);
        $episode->save();

        return response()->json([
            'success' => true,
            'message' => 'Episode updated.',
            'data' => $this->formatEpisode($episode),
        ]);
    }

    /**
     * Delete a season (cascades episodes).
     */
    public function destroy(Request $request, int $seasonId): JsonResponse
    {
        $user = $request->user();
        $season = Season::with('tvShow')->find($seasonId);

        if (!$season || !$season->tvShow) {
            return response()->json(['success' => false, 'message' => 'Season not found.'], 404);
        }

        $show = $this->creatorTvShowQuery($user)->find($season->tv_show_id);
        if (!$show) {
            return response()->json(['success' => false, 'message' => 'Not authorized.'], 403);
        }

        $season->delete();
        $this->refreshTvShowCounts($show);

        return response()->json(['success' => true, 'message' => 'Season deleted.']);
    }

    /**
     * Delete an episode.
     */
    public function destroyEpisode(Request $request, int $episodeId): JsonResponse
    {
        $user = $request->user();
        $episode = Episode::with('season')->find($episodeId);

        if (!$episode || !$episode->season) {
            return response()->json(['success' => false, 'message' => 'Episode not found.'], 404);
        }

        $show = $this->creatorTvShowQuery($user)->find($episode->season->tv_show_id);
        if (!$show) {
            return response()->json(['success' => false, 'message' => 'Not authorized.'], 403);
        }

        $episode->delete();
        $this->refreshTvShowCounts($show);

        return response()->json(['success' => true, 'message' => 'Episode deleted.']);
    }

    public function duplicateEpisode(Request $request, int $episodeId): JsonResponse
    {
        $user = $request->user();
        $episode = Episode::with('season')->findOrFail($episodeId);
        abort_unless(
            $episode->season
            && $this->creatorTvShowQuery($user)->whereKey($episode->season->tv_show_id)->exists(),
            404
        );
        $validated = $request->validate([
            'episode_number' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ]);
        $number = (int) ($validated['episode_number']
            ?? (Episode::where('season_id', $episode->season_id)->max('number') + 1));
        abort_if(
            Episode::where('season_id', $episode->season_id)->where('number', $number)->exists(),
            422,
            "Episode {$number} already exists."
        );

        $copy = $episode->replicate([
            'submitted_by', 'creator_application_id', 'processing_status',
            'editorial_status', 'publication_status', 'monetization_status',
            'submitted_for_review_at', 'approved_by', 'approved_at', 'published_by',
            'published_at', 'moderation_notes',
        ]);
        $copy->number = $number;
        $copy->title = $episode->title.' (Copy)';
        $copy->video_url = null;
        $copy->is_active = false;
        $copy->save();
        $this->workflow->initialize($copy, $user, 'duplicate_metadata');
        $this->refreshTvShowCounts($episode->season->tvShow);

        return response()->json([
            'success' => true,
            'message' => 'Episode metadata duplicated. Add its own media source before review.',
            'data' => $this->formatEpisode($copy),
        ], 201);
    }

    public function reorderEpisodes(Request $request, int $seasonId): JsonResponse
    {
        $user = $request->user();
        $season = Season::with('tvShow')->findOrFail($seasonId);
        abort_unless(
            $season->tvShow
            && $this->creatorTvShowQuery($user)->whereKey($season->tv_show_id)->exists(),
            404
        );
        $validated = $request->validate([
            'episodes' => ['required', 'array', 'min:1'],
            'episodes.*.id' => ['required', 'integer'],
            'episodes.*.episode_number' => ['required', 'integer', 'min:1', 'max:10000', 'distinct'],
        ]);
        $ownedIds = $season->episodes()->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        $requestedIds = collect($validated['episodes'])->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        abort_unless($ownedIds === $requestedIds, 422, 'The reorder list must contain every episode in this season exactly once.');

        DB::transaction(function () use ($validated, $season): void {
            foreach ($validated['episodes'] as $index => $entry) {
                Episode::where('season_id', $season->id)->whereKey($entry['id'])->update(['number' => -($index + 1)]);
            }
            foreach ($validated['episodes'] as $entry) {
                Episode::where('season_id', $season->id)->whereKey($entry['id'])->update(['number' => $entry['episode_number']]);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Episode order saved.',
            'data' => $this->formatSeason($season->refresh()),
        ]);
    }

    public function publishEpisode(Request $request, int $episodeId): JsonResponse
    {
        $user = $request->user();
        $episode = Episode::with('season')->findOrFail($episodeId);
        abort_unless(
            $episode->season
            && $this->creatorTvShowQuery($user)->whereKey($episode->season->tv_show_id)->exists(),
            404
        );

        $this->workflow->submitForReview($episode, $user, $request->boolean('publish_after_approval'));

        return response()->json([
            'success' => true,
            'message' => 'Episode submitted for review.',
            'data' => $this->formatEpisode($episode->refresh()),
        ]);
    }

    private function refreshTvShowCounts($show): void
    {
        $show->refresh();
        $seasonsCount = $show->seasons()->count();
        $episodesCount = Episode::whereIn('season_id', $show->seasons()->pluck('id'))->count();
        $show->update([
            'number_of_seasons' => $seasonsCount,
            'number_of_episodes' => $episodesCount,
        ]);
    }

    private function formatSeason(Season $season): array
    {
        $season->load('episodes.videoSources');
        return [
            'id' => $season->id,
            'season_number' => $season->number,
            'title' => $season->title,
            'air_date' => $season->air_date?->format('Y-m-d'),
            'episode_count' => $season->episodes->count(),
            'episodes' => $season->episodes->map(fn($ep) => $this->formatEpisode($ep)),
        ];
    }

    private function formatEpisode(Episode $episode): array
    {
        return [
            'id' => $episode->id,
            'episode_number' => $episode->number,
            'title' => $episode->title,
            'description' => $episode->description,
            'duration' => $episode->duration,
            'thumbnail' => $episode->thumbnail,
            'release_date' => $episode->release_date?->format('Y-m-d'),
            'translation_language' => $episode->translation_language,
            'short_description' => $episode->short_description,
            'tags' => $episode->tags ?? [],
            'download_enabled' => (bool) $episode->download_enabled,
            'is_active' => (bool) $episode->is_active,
            'processing_status' => $episode->processing_status ?? 'not_started',
            'editorial_status' => $episode->editorial_status ?? 'not_submitted',
            'publication_status' => $episode->publication_status ?? 'draft',
            'monetization_status' => $episode->monetization_status ?? 'disabled',
            'sources_count' => $episode->relationLoaded('videoSources') ? $episode->videoSources->count() : $episode->videoSources()->count(),
            'sources' => $episode->relationLoaded('videoSources')
                ? $episode->videoSources->map(fn($source) => $this->formatVideoSource($source))
                : [],
        ];
    }
}
