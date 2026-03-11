<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TmdbService
{
    private const BASE_URL = 'https://api.themoviedb.org/3';
    private const API_KEY = '63591f9e68a2c53bbd5d838a868d9727';
    private const IMAGE_BASE_URL = 'https://image.tmdb.org/t/p';

    /**
     * Search for movies or TV shows by name
     */
    public function search(string $query, string $type = 'multi'): array
    {
        try {
            $endpoint = $type === 'multi' ? '/search/multi' : '/search/' . $type;
            $response = Http::timeout(10)->get(self::BASE_URL . $endpoint, [
                'api_key' => self::API_KEY,
                'query' => $query,
                'language' => 'en-US',
            ]);

            if ($response->successful()) {
                $data = $response->json();
                // Filter to only return movies or TV shows based on type
                if ($type === 'multi' && isset($data['results'])) {
                    $data['results'] = array_filter($data['results'], function($item) {
                        return in_array($item['media_type'] ?? '', ['movie', 'tv']);
                    });
                }
                return $data;
            }

            Log::warning('TMDB Search Failed: ' . $response->status());
            return ['results' => []];
        } catch (\Exception $e) {
            Log::error('TMDB Search Error: ' . $e->getMessage());
            return ['results' => []];
        }
    }

    /**
     * Get movie details by TMDB ID
     */
    public function getMovieDetails(int $tmdbId): ?array
    {
        try {
            $response = Http::get(self::BASE_URL . "/movie/{$tmdbId}", [
                'api_key' => self::API_KEY,
                'language' => 'en-US',
                'append_to_response' => 'videos,credits,keywords,release_dates,external_ids,recommendations,similar',
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            return null;
        } catch (\Exception $e) {
            Log::error('TMDB Movie Details Error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get TV show details by TMDB ID
     */
    public function getTvShowDetails(int $tmdbId): ?array
    {
        try {
            $response = Http::timeout(15)->get(self::BASE_URL . "/tv/{$tmdbId}", [
                'api_key' => self::API_KEY,
                'language' => 'en-US',
                'append_to_response' => 'videos,credits,keywords,external_ids,recommendations,similar,content_ratings',
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning('TMDB TV Show Details Failed: ' . $response->status() . ' - ' . $response->body());
            return null;
        } catch (\Exception $e) {
            Log::error('TMDB TV Show Details Error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get TV show season details
     */
    public function getSeasonDetails(int $tvId, int $seasonNumber): ?array
    {
        try {
            $response = Http::get(self::BASE_URL . "/tv/{$tvId}/season/{$seasonNumber}", [
                'api_key' => self::API_KEY,
                'language' => 'en-US',
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            return null;
        } catch (\Exception $e) {
            Log::error('TMDB Season Details Error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Download and save image from TMDB
     */
    public function downloadImage(string $imagePath, string $size = 'w500', string $type = 'poster'): ?string
    {
        if (empty($imagePath)) {
            return null;
        }

        try {
            // Ensure imagePath starts with '/' if it doesn't
            if (!str_starts_with($imagePath, '/')) {
                $imagePath = '/' . $imagePath;
            }
            
            // Build the full TMDB image URL
            // Format: https://image.tmdb.org/t/p/{size}{path}
            $imageUrl = self::IMAGE_BASE_URL . '/' . $size . $imagePath;
            
            $extension = pathinfo($imagePath, PATHINFO_EXTENSION) ?: 'jpg';
            $fileName = 'tmdb_' . md5($imagePath . $size) . '.' . $extension;
            $directory = "tmdb/{$type}s";
            $filePath = "{$directory}/{$fileName}";

            // Create directory if it doesn't exist
            if (!Storage::disk('public')->exists($directory)) {
                Storage::disk('public')->makeDirectory($directory, 0755, true);
            }

            $fullPath = Storage::disk('public')->path($filePath);

            // Download image with proper headers and validation
            $response = Http::timeout(30)
                ->withHeaders([
                    'Accept' => 'image/*',
                ])
                ->get($imageUrl);

            if (!$response->successful()) {
                Log::error('TMDB Image Download Failed: HTTP ' . $response->status() . ' - ' . $imageUrl);
                return null;
            }

            $imageContent = $response->body();
            
            // Validate it's actually an image (check magic bytes)
            $imageInfo = @getimagesizefromstring($imageContent);
            if ($imageInfo === false) {
                Log::error('TMDB Image Download: Invalid image data received - ' . $imageUrl);
                // Check if it's HTML (error page)
                if (str_starts_with(trim($imageContent), '<')) {
                    Log::error('TMDB Image Download: Received HTML instead of image - ' . substr($imageContent, 0, 200));
                }
                return null;
            }

            // Save the image
            file_put_contents($fullPath, $imageContent);

            // Verify file was saved correctly
            if (!file_exists($fullPath) || filesize($fullPath) === 0) {
                Log::error('TMDB Image Download: File save failed - ' . $fullPath);
                return null;
            }

            return $filePath;
        } catch (\Exception $e) {
            Log::error('TMDB Image Download Error: ' . $e->getMessage() . ' - URL: ' . ($imageUrl ?? 'N/A'));
            return null;
        }
    }

    /**
     * Get full image URL from TMDB path
     */
    public function getImageUrl(?string $imagePath, string $size = 'w500'): ?string
    {
        if (empty($imagePath)) {
            return null;
        }

        return self::IMAGE_BASE_URL . '/' . $size . $imagePath;
    }

    /**
     * Format movie data from TMDB for our database
     */
    public function formatMovieData(array $tmdbData): array
    {
        return [
            'tmdb_id' => $tmdbData['id'] ?? null,
            'imdb_id' => $tmdbData['external_ids']['imdb_id'] ?? null,
            'title' => $tmdbData['title'] ?? '',
            'description' => $tmdbData['overview'] ?? '',
            'thumbnail' => $this->getImageUrl($tmdbData['poster_path'] ?? null),
            'backdrop' => $this->getImageUrl($tmdbData['backdrop_path'] ?? null, 'w1280'),
            'rating' => isset($tmdbData['vote_average']) ? round($tmdbData['vote_average'], 1) : 0,
            'release_date' => $tmdbData['release_date'] ?? null,
            'duration' => isset($tmdbData['runtime']) && $tmdbData['runtime'] ? $this->formatRuntime($tmdbData['runtime']) : null,
            'budget' => $tmdbData['budget'] ?? null,
            'revenue' => $tmdbData['revenue'] ?? null,
            'tagline' => $tmdbData['tagline'] ?? null,
            'status' => $tmdbData['status'] ?? null,
            'original_language' => $tmdbData['original_language'] ?? null,
            'original_title' => $tmdbData['original_title'] ?? '',
            'popularity' => $tmdbData['popularity'] ?? 0,
            'vote_count' => $tmdbData['vote_count'] ?? 0,
            'homepage' => $tmdbData['homepage'] ?? null,
            'certificate' => $this->extractCertificate(
                isset($tmdbData['release_dates']['results']) 
                    ? $tmdbData['release_dates']['results'] 
                    : []
            ),
            'country' => $this->extractCountry($tmdbData['production_countries'] ?? []),
            'language' => $this->extractLanguage($tmdbData['spoken_languages'] ?? []),
            'genres' => $this->extractGenres($tmdbData['genres'] ?? []),
            'cast' => $this->extractCast($tmdbData['credits']['cast'] ?? []),
            'crew' => $this->extractCrew($tmdbData['credits']['crew'] ?? []),
            'trailers' => $this->extractTrailers($tmdbData['videos']['results'] ?? []),
            'keywords' => $this->extractKeywords($tmdbData['keywords']['keywords'] ?? []),
            'collection' => $tmdbData['belongs_to_collection'] ?? null,
            'production_companies' => $tmdbData['production_companies'] ?? [],
            'production_countries' => $tmdbData['production_countries'] ?? [],
        ];
    }

    /**
     * Format TV show data from TMDB for our database
     */
    public function formatTvShowData(array $tmdbData): array
    {
        return [
            'tmdb_id' => $tmdbData['id'] ?? null,
            'imdb_id' => $tmdbData['external_ids']['imdb_id'] ?? null,
            'title' => $tmdbData['name'] ?? '',
            'description' => $tmdbData['overview'] ?? '',
            'thumbnail' => $this->getImageUrl($tmdbData['poster_path'] ?? null),
            'backdrop' => $this->getImageUrl($tmdbData['backdrop_path'] ?? null, 'w1280'),
            'rating' => isset($tmdbData['vote_average']) ? round($tmdbData['vote_average'], 1) : 0,
            'release_date' => $tmdbData['first_air_date'] ?? null,
            'duration' => isset($tmdbData['episode_run_time'][0]) && $tmdbData['episode_run_time'][0] ? $this->formatRuntime($tmdbData['episode_run_time'][0]) : null,
            'tagline' => null, // TV shows don't have taglines
            'status' => $tmdbData['status'] ?? null,
            'original_language' => $tmdbData['original_language'] ?? null,
            'original_title' => $tmdbData['original_name'] ?? '',
            'popularity' => $tmdbData['popularity'] ?? 0,
            'vote_count' => $tmdbData['vote_count'] ?? 0,
            'homepage' => $tmdbData['homepage'] ?? null,
            'certificate' => $this->extractTvCertificate($tmdbData['content_ratings']['results'] ?? []),
            'country' => $this->extractCountry($tmdbData['production_countries'] ?? []),
            'language' => $this->extractLanguage($tmdbData['spoken_languages'] ?? []),
            'genres' => $this->extractGenres($tmdbData['genres'] ?? []),
            'cast' => $this->extractCast($tmdbData['credits']['cast'] ?? []),
            'crew' => $this->extractCrew($tmdbData['credits']['crew'] ?? []),
            'trailers' => $this->extractTrailers($tmdbData['videos']['results'] ?? []),
            'keywords' => $this->extractKeywords($tmdbData['keywords']['results'] ?? []),
            'number_of_seasons' => $tmdbData['number_of_seasons'] ?? 0,
            'number_of_episodes' => $tmdbData['number_of_episodes'] ?? 0,
            'seasons' => $tmdbData['seasons'] ?? [],
            'networks' => $tmdbData['networks'] ?? [],
            'production_companies' => $tmdbData['production_companies'] ?? [],
            'production_countries' => $tmdbData['production_countries'] ?? [],
        ];
    }

    public function formatRuntime(int $minutes): string
    {
        $hours = floor($minutes / 60);
        $mins = $minutes % 60;
        if ($hours > 0) {
            return "{$hours}h {$mins}m";
        }
        return "{$mins}m";
    }

    private function extractCertificate(array $releaseDates): ?string
    {
        // Extract US certification
        foreach ($releaseDates as $country) {
            if (isset($country['iso_3166_1']) && $country['iso_3166_1'] === 'US' && !empty($country['release_dates'])) {
                return $country['release_dates'][0]['certification'] ?? null;
            }
        }
        return null;
    }

    private function extractTvCertificate(array $contentRatings): ?string
    {
        // Extract US rating
        foreach ($contentRatings as $rating) {
            if (isset($rating['iso_3166_1']) && $rating['iso_3166_1'] === 'US') {
                return $rating['rating'] ?? null;
            }
        }
        return null;
    }

    private function extractCountry(array $countries): ?string
    {
        if (empty($countries) || !isset($countries[0]['name'])) {
            return null;
        }
        return $countries[0]['name'];
    }

    private function extractLanguage(array $languages): ?string
    {
        if (empty($languages) || !isset($languages[0]['english_name'])) {
            return null;
        }
        return $languages[0]['english_name'];
    }

    private function extractGenres(array $genres): array
    {
        return array_map(function($g) {
            return $g['name'] ?? '';
        }, array_filter($genres, fn($g) => isset($g['name'])));
    }

    private function extractCast(array $cast): array
    {
        return array_map(function ($actor) {
            return [
                'tmdb_id' => $actor['id'] ?? null,
                'name' => $actor['name'] ?? '',
                'character' => $actor['character'] ?? '',
                'order' => $actor['order'] ?? 0,
                'profile_path' => $actor['profile_path'] ?? null, // Return raw path, not full URL
            ];
        }, array_slice($cast, 0, 20)); // Limit to top 20
    }

    private function extractCrew(array $crew): array
    {
        $directors = [];
        $writers = [];
        $producers = [];

        foreach ($crew as $member) {
            if (!isset($member['id']) || !isset($member['name'])) {
                continue;
            }

            $person = [
                'tmdb_id' => $member['id'],
                'name' => $member['name'],
                'job' => $member['job'] ?? '',
                'department' => $member['department'] ?? '',
                'profile_path' => $member['profile_path'] ?? null, // Return raw path, not full URL
            ];

            $job = $member['job'] ?? '';
            $department = $member['department'] ?? '';

            if ($job === 'Director') {
                $directors[] = $person;
            } elseif (in_array($job, ['Writer', 'Screenplay', 'Story'])) {
                $writers[] = $person;
            } elseif ($department === 'Production') {
                $producers[] = $person;
            }
        }

        return [
            'directors' => $directors,
            'writers' => $writers,
            'producers' => $producers,
        ];
    }

    private function extractTrailers(array $videos): array
    {
        return array_map(function ($video) {
            return [
                'tmdb_id' => $video['id'] ?? null,
                'key' => $video['key'] ?? '',
                'name' => $video['name'] ?? '',
                'site' => $video['site'] ?? 'YouTube', // YouTube, Vimeo
                'type' => $video['type'] ?? 'Trailer', // Trailer, Teaser, etc.
                'size' => $video['size'] ?? null,
            ];
        }, array_filter($videos, function($v) {
            return isset($v['site']) && isset($v['type']) && 
                   $v['site'] === 'YouTube' && $v['type'] === 'Trailer';
        }));
    }

    private function extractKeywords(array $keywords): array
    {
        return array_map(function($k) {
            return $k['name'] ?? '';
        }, array_filter($keywords, fn($k) => isset($k['name'])));
    }
}

