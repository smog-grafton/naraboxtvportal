<?php

namespace App\Http\Controllers\Api\Creator;

use App\Services\TmdbService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreatorTmdbController extends CreatorBaseController
{
    public function __construct(private readonly TmdbService $tmdb)
    {
    }

    /**
     * Search TMDB for movies or TV shows.
     */
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q'    => ['required', 'string', 'min:2', 'max:200'],
            'type' => ['nullable', 'in:movie,tv,multi'],
        ]);

        $type = $validated['type'] ?? 'multi';
        $raw = $this->tmdb->search($validated['q'], $type);
        $items = $raw['results'] ?? [];

        // Normalize results to a consistent format
        $formatted = collect($items)->map(function ($item) {
            $mediaType = $item['media_type'] ?? 'movie';
            $isMovie = $mediaType === 'movie';

            return [
                'tmdb_id'    => $item['id'],
                'media_type' => $mediaType,
                'title'      => $isMovie ? ($item['title'] ?? $item['original_title'] ?? '') : ($item['name'] ?? $item['original_name'] ?? ''),
                'year'       => $isMovie
                    ? substr($item['release_date'] ?? '', 0, 4)
                    : substr($item['first_air_date'] ?? '', 0, 4),
                'overview'   => $item['overview'] ?? '',
                'poster_url' => $this->tmdb->getImageUrl($item['poster_path'] ?? null, 'w342'),
                'backdrop_url' => $this->tmdb->getImageUrl($item['backdrop_path'] ?? null, 'w780'),
                'rating'     => round((float) ($item['vote_average'] ?? 0), 1),
                'vote_count' => (int) ($item['vote_count'] ?? 0),
                'popularity' => round((float) ($item['popularity'] ?? 0), 2),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data'    => $formatted,
            'query'   => $validated['q'],
            'type'    => $type,
        ]);
    }

    /**
     * Get full TMDB movie details formatted for pre-filling the create movie form.
     */
    public function movie(Request $request, int $tmdbId): JsonResponse
    {
        $raw = $this->tmdb->getMovieDetails($tmdbId);

        if (!$raw) {
            return response()->json(['success' => false, 'message' => 'TMDB movie not found.'], 404);
        }

        $formatted = $this->tmdb->formatMovieData($raw);

        // Download images and get local storage paths
        $thumbnailPath = null;
        $backdropPath = null;

        if (!empty($raw['poster_path'])) {
            $thumbnailPath = $this->tmdb->downloadImage($raw['poster_path'], 'w500', 'poster');
        }
        if (!empty($raw['backdrop_path'])) {
            $backdropPath = $this->tmdb->downloadImage($raw['backdrop_path'], 'w1280', 'backdrop');
        }

        return response()->json([
            'success' => true,
            'data'    => array_merge($formatted, [
                'tmdb_id'           => $raw['id'],
                'poster_url'        => $this->tmdb->getImageUrl($raw['poster_path'] ?? null, 'w500'),
                'backdrop_url'      => $this->tmdb->getImageUrl($raw['backdrop_path'] ?? null, 'w1280'),
                'local_thumbnail'   => $thumbnailPath,
                'local_backdrop'    => $backdropPath,
                'genres'            => collect($raw['genres'] ?? [])->map(fn($g) => [
                    'tmdb_id' => $g['id'],
                    'name'    => $g['name'],
                ])->all(),
                'cast'              => collect($raw['credits']['cast'] ?? [])->take(10)->map(fn($c) => [
                    'name'      => $c['name'],
                    'character' => $c['character'],
                    'profile_url' => $this->tmdb->getImageUrl($c['profile_path'] ?? null, 'w185'),
                ])->all(),
            ]),
        ]);
    }

    /**
     * Get full TMDB TV show details formatted for pre-filling the create TV show form.
     */
    public function tv(Request $request, int $tmdbId): JsonResponse
    {
        $raw = $this->tmdb->getTvShowDetails($tmdbId);

        if (!$raw) {
            return response()->json(['success' => false, 'message' => 'TMDB TV show not found.'], 404);
        }

        $formatted = $this->tmdb->formatTvShowData($raw);

        $thumbnailPath = null;
        $backdropPath = null;

        if (!empty($raw['poster_path'])) {
            $thumbnailPath = $this->tmdb->downloadImage($raw['poster_path'], 'w500', 'poster');
        }
        if (!empty($raw['backdrop_path'])) {
            $backdropPath = $this->tmdb->downloadImage($raw['backdrop_path'], 'w1280', 'backdrop');
        }

        return response()->json([
            'success' => true,
            'data'    => array_merge($formatted, [
                'tmdb_id'           => $raw['id'],
                'poster_url'        => $this->tmdb->getImageUrl($raw['poster_path'] ?? null, 'w500'),
                'backdrop_url'      => $this->tmdb->getImageUrl($raw['backdrop_path'] ?? null, 'w1280'),
                'local_thumbnail'   => $thumbnailPath,
                'local_backdrop'    => $backdropPath,
                'genres'            => collect($raw['genres'] ?? [])->map(fn($g) => [
                    'tmdb_id' => $g['id'],
                    'name'    => $g['name'],
                ])->all(),
                'cast'              => collect($raw['credits']['cast'] ?? [])->take(10)->map(fn($c) => [
                    'name'      => $c['name'],
                    'character' => $c['character'],
                    'profile_url' => $this->tmdb->getImageUrl($c['profile_path'] ?? null, 'w185'),
                ])->all(),
                'seasons'           => collect($raw['seasons'] ?? [])->map(fn($s) => [
                    'season_number' => $s['season_number'],
                    'episode_count' => $s['episode_count'],
                    'air_date'      => $s['air_date'],
                    'name'          => $s['name'],
                    'poster_url'    => $this->tmdb->getImageUrl($s['poster_path'] ?? null, 'w185'),
                ])->all(),
            ]),
        ]);
    }

    /**
     * Get TMDB TV show season details (full episode list).
     */
    public function tvSeason(Request $request, int $tmdbTvId, int $seasonNumber): JsonResponse
    {
        $raw = $this->tmdb->getSeasonDetails($tmdbTvId, $seasonNumber);

        if (!$raw) {
            return response()->json(['success' => false, 'message' => 'TMDB season not found.'], 404);
        }

        $episodes = collect($raw['episodes'] ?? [])->map(fn($ep) => [
            'episode_number' => $ep['episode_number'] ?? 0,
            'name' => $ep['name'] ?? '',
            'overview' => $ep['overview'] ?? '',
            'air_date' => $ep['air_date'] ?? null,
            'runtime' => $ep['runtime'] ?? null,
            'still_url' => $this->tmdb->getImageUrl($ep['still_path'] ?? null, 'w342'),
        ])->all();

        return response()->json([
            'success' => true,
            'data' => [
                'season_number' => $raw['season_number'] ?? $seasonNumber,
                'name' => $raw['name'] ?? 'Season ' . $seasonNumber,
                'overview' => $raw['overview'] ?? '',
                'air_date' => $raw['air_date'] ?? null,
                'episodes' => $episodes,
            ],
        ]);
    }
}
