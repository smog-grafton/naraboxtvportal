<?php

namespace App\Http\Controllers\Api\Creator;

use App\Models\Episode;
use App\Models\Season;
use App\Services\TmdbService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreatorSeasonController extends CreatorBaseController
{
    public function __construct(private readonly TmdbService $tmdb)
    {
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

        $validated = $request->validate([
            'season_number' => ['required', 'integer', 'min:1'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'air_date' => ['nullable', 'date'],
        ]);

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

                Episode::create([
                    'season_id' => $season->id,
                    'number' => $epNum,
                    'title' => $ep['name'] ?? 'Episode ' . $epNum,
                    'description' => $ep['overview'] ?? null,
                    'duration' => isset($ep['runtime']) && $ep['runtime'] ? $ep['runtime'] . ' min' : null,
                    'thumbnail' => $this->tmdb->getImageUrl($ep['still_path'] ?? null, 'w342'),
                ]);
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

        $validated = $request->validate([
            'episode_number' => ['required', 'integer', 'min:1'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'duration' => ['nullable', 'string', 'max:20'],
            'thumbnail' => ['nullable', 'string', 'max:2048'],
        ]);

        if (Episode::where('season_id', $season->id)->where('number', $validated['episode_number'])->exists()) {
            return response()->json(['success' => false, 'message' => 'Episode ' . $validated['episode_number'] . ' already exists.'], 422);
        }

        $episode = Episode::create([
            'season_id' => $season->id,
            'number' => $validated['episode_number'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'duration' => $validated['duration'] ?? null,
            'thumbnail' => $validated['thumbnail'] ?? null,
        ]);

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

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'air_date' => ['nullable', 'date'],
        ]);

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

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'duration' => ['nullable', 'string', 'max:20'],
            'thumbnail' => ['nullable', 'string', 'max:2048'],
        ]);

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
            'is_active' => true,
            'sources_count' => $episode->relationLoaded('videoSources') ? $episode->videoSources->count() : $episode->videoSources()->count(),
            'sources' => $episode->relationLoaded('videoSources')
                ? $episode->videoSources->map(fn($source) => $this->formatVideoSource($source))
                : [],
        ];
    }
}
