<?php

namespace Database\Seeders;

use App\Models\Movie;
use App\Models\Category;
use App\Models\Genre;
use App\Models\VJ;
use App\Models\Season;
use App\Models\Episode;
use App\Models\Actor;
use Illuminate\Database\Seeder;

class MovieSeeder extends Seeder
{
    private const TEST_MP4 = 'https://fxtrias.com/movies/HIT2D_inverse_energy_cascade.mp4';

    public function run(): void
    {
        $categories = Category::all()->keyBy('name');
        $genres = Genre::all()->keyBy('name');
        $vjs = VJ::all()->keyBy('name');
        $actors = Actor::all();

        // Movies
        $movies = [
            [
                'title' => 'The Silent VJ: Origins',
                'description' => 'A translated masterpiece of a world that went quiet.',
                'thumbnail' => 'https://images.unsplash.com/photo-1536440136628-849c177e76a1?w=400&h=600&fit=crop',
                'backdrop' => 'https://images.unsplash.com/photo-1536440136628-849c177e76a1?w=1920&h=1080&fit=crop',
                'rating' => 9.8,
                'release_date' => '2024-11-01',
                'category' => 'VJ Translated',
                'media_type' => 'MOVIE',
                'vj' => 'VJ Junior',
                'genres' => ['Mystery', 'Sci-Fi'],
                'trending_score' => 99,
                'access_type' => 'PREMIUM',
                'video_url' => self::TEST_MP4,
                'price_rent' => 5000,
                'price_buy' => 25000,
                'is_featured' => true,
                'featured_order' => 1,
            ],
            [
                'title' => 'Kampala Nights',
                'description' => 'High stakes, high speed, and local dialect.',
                'thumbnail' => 'https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?w=400&h=600&fit=crop',
                'backdrop' => 'https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?w=1920&h=1080&fit=crop',
                'rating' => 8.5,
                'release_date' => '2024-05-15',
                'category' => 'VJ Translated',
                'media_type' => 'MOVIE',
                'vj' => 'VJ Jingo',
                'genres' => ['Action', 'Crime'],
                'access_type' => 'RENT',
                'video_url' => self::TEST_MP4,
                'price_rent' => 3500,
                'is_featured' => true,
                'featured_order' => 2,
            ],
            [
                'title' => 'Cyber Enigma',
                'description' => 'In a world where digital consciousness has become reality, a rogue AI threatens to merge human minds with the virtual realm.',
                'thumbnail' => 'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?w=400&h=600&fit=crop',
                'backdrop' => 'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?w=1920&h=1080&fit=crop',
                'rating' => 7.9,
                'release_date' => '2024-08-20',
                'category' => 'Movie',
                'media_type' => 'MOVIE',
                'vj' => 'VJ Jingo',
                'genres' => ['Sci-Fi', 'Thriller'],
                'access_type' => 'FREE',
                'video_url' => self::TEST_MP4,
                'duration' => '2h 15m',
            ],
            [
                'title' => 'Lost in the Rift',
                'description' => 'Space exploration.',
                'thumbnail' => 'https://images.unsplash.com/photo-1446776811953-b23d57bd21aa?w=400&h=600&fit=crop',
                'backdrop' => 'https://images.unsplash.com/photo-1446776811953-b23d57bd21aa?w=1920&h=1080&fit=crop',
                'rating' => 9.1,
                'release_date' => '2023-12-10',
                'category' => 'Movie',
                'media_type' => 'MOVIE',
                'genres' => ['Adventure'],
                'access_type' => 'PREMIUM',
                'video_url' => self::TEST_MP4,
            ],
            [
                'title' => 'The Iron Fist',
                'description' => 'Martial arts drama.',
                'thumbnail' => 'https://images.unsplash.com/photo-1552072092-7f9b8d63efcb?w=400&h=600&fit=crop',
                'backdrop' => 'https://images.unsplash.com/photo-1552072092-7f9b8d63efcb?w=1920&h=1080&fit=crop',
                'rating' => 8.2,
                'release_date' => '2024-01-05',
                'category' => 'Movie',
                'media_type' => 'MOVIE',
                'genres' => ['Action'],
                'access_type' => 'FREE',
                'video_url' => self::TEST_MP4,
            ],
            [
                'title' => 'Shadow Walker',
                'description' => 'A master ninja emerges from the shadows to protect a secret that could change the world.',
                'thumbnail' => 'https://images.unsplash.com/photo-1518709268805-4e9042af9f23?w=400&h=600&fit=crop',
                'backdrop' => 'https://images.unsplash.com/photo-1518709268805-4e9042af9f23?w=1920&h=1080&fit=crop',
                'rating' => 8.7,
                'release_date' => '2024-02-14',
                'category' => 'Movie',
                'media_type' => 'MOVIE',
                'vj' => 'VJ Mark',
                'genres' => ['Action', 'Thriller'],
                'access_type' => 'RENT',
                'video_url' => self::TEST_MP4,
                'price_rent' => 4000,
                'duration' => '1h 58m',
            ],
            [
                'title' => 'Neon Pulse',
                'description' => 'Cyberpunk music.',
                'thumbnail' => 'https://images.unsplash.com/photo-1550684848-fac1c5b4e853?w=400&h=600&fit=crop',
                'backdrop' => 'https://images.unsplash.com/photo-1550684848-fac1c5b4e853?w=1920&h=1080&fit=crop',
                'rating' => 7.5,
                'release_date' => '2023-11-20',
                'category' => 'Movie',
                'media_type' => 'MOVIE',
                'genres' => ['Music', 'Sci-Fi'],
                'access_type' => 'FREE',
                'video_url' => self::TEST_MP4,
            ],
            [
                'title' => 'Ancient Echoes',
                'description' => 'When a team of archaeologists discovers a lost civilization buried beneath the desert sands.',
                'thumbnail' => 'https://images.unsplash.com/photo-1503174971373-b1f69850bded?w=400&h=600&fit=crop',
                'backdrop' => 'https://images.unsplash.com/photo-1503174971373-b1f69850bded?w=1920&h=1080&fit=crop',
                'rating' => 8.9,
                'release_date' => '2024-09-01',
                'category' => 'Movie',
                'media_type' => 'MOVIE',
                'vj' => 'VJ Junior',
                'genres' => ['Mystery', 'Adventure'],
                'access_type' => 'PREMIUM',
                'video_url' => self::TEST_MP4,
                'duration' => '2h 32m',
            ],
            [
                'title' => 'Deep Frost',
                'description' => 'Arctic survival.',
                'thumbnail' => 'https://images.unsplash.com/photo-1517783999520-f068d7431a60?w=400&h=600&fit=crop',
                'backdrop' => 'https://images.unsplash.com/photo-1517783999520-f068d7431a60?w=1920&h=1080&fit=crop',
                'rating' => 8.4,
                'release_date' => '2024-03-30',
                'category' => 'Movie',
                'media_type' => 'MOVIE',
                'genres' => ['Thriller', 'Action'],
                'access_type' => 'RENT',
                'video_url' => self::TEST_MP4,
                'price_rent' => 4500,
            ],
            [
                'title' => 'Midnight Rain',
                'description' => 'Film noir.',
                'thumbnail' => 'https://images.unsplash.com/photo-1485846234645-a62644f84728?w=400&h=600&fit=crop',
                'backdrop' => 'https://images.unsplash.com/photo-1485846234645-a62644f84728?w=1920&h=1080&fit=crop',
                'rating' => 7.8,
                'release_date' => '2023-10-15',
                'category' => 'Movie',
                'media_type' => 'MOVIE',
                'vj' => 'VJ Emmy',
                'genres' => ['Crime', 'Drama'],
                'access_type' => 'FREE',
                'video_url' => self::TEST_MP4,
            ],
            [
                'title' => 'Desert Storm',
                'description' => 'War epic.',
                'thumbnail' => 'https://images.unsplash.com/photo-1509062522246-3755977927d7?w=400&h=600&fit=crop',
                'backdrop' => 'https://images.unsplash.com/photo-1509062522246-3755977927d7?w=1920&h=1080&fit=crop',
                'rating' => 9.3,
                'release_date' => '2024-06-22',
                'category' => 'Movie',
                'media_type' => 'MOVIE',
                'genres' => ['Action', 'History'],
                'access_type' => 'BUY',
                'video_url' => self::TEST_MP4,
                'price_buy' => 15000,
            ],
            [
                'title' => 'The Last Stand',
                'description' => 'Western showdown.',
                'thumbnail' => 'https://images.unsplash.com/photo-1533929736458-ca588d08c8be?w=400&h=600&fit=crop',
                'backdrop' => 'https://images.unsplash.com/photo-1533929736458-ca588d08c8be?w=1920&h=1080&fit=crop',
                'rating' => 8.1,
                'release_date' => '2024-07-04',
                'category' => 'Movie',
                'media_type' => 'MOVIE',
                'genres' => ['Action', 'Western'],
                'access_type' => 'FREE',
                'video_url' => self::TEST_MP4,
            ],
            [
                'title' => 'Quantum Drift',
                'description' => 'Time travel racing.',
                'thumbnail' => 'https://images.unsplash.com/photo-1542281286-9e0a16bb7366?w=400&h=600&fit=crop',
                'backdrop' => 'https://images.unsplash.com/photo-1542281286-9e0a16bb7366?w=1920&h=1080&fit=crop',
                'rating' => 8.6,
                'release_date' => '2024-08-11',
                'category' => 'Movie',
                'media_type' => 'MOVIE',
                'vj' => 'VJ Jingo',
                'genres' => ['Sci-Fi', 'Racing'],
                'access_type' => 'RENT',
                'video_url' => self::TEST_MP4,
                'price_rent' => 5500,
            ],
            [
                'title' => 'Siren Call',
                'description' => 'Ocean mystery.',
                'thumbnail' => 'https://images.unsplash.com/photo-1505118380757-91f5f5632de0?w=400&h=600&fit=crop',
                'backdrop' => 'https://images.unsplash.com/photo-1505118380757-91f5f5632de0?w=1920&h=1080&fit=crop',
                'rating' => 7.2,
                'release_date' => '2024-10-31',
                'category' => 'Movie',
                'media_type' => 'MOVIE',
                'genres' => ['Horror', 'Mystery'],
                'access_type' => 'FREE',
                'video_url' => self::TEST_MP4,
            ],
        ];

        // Series
        $series = [
            [
                'title' => 'The Silent VJ: Series',
                'description' => 'Expanding the universe of silence.',
                'thumbnail' => 'https://images.unsplash.com/photo-1536440136628-849c177e76a1?w=400&h=600&fit=crop',
                'backdrop' => 'https://images.unsplash.com/photo-1536440136628-849c177e76a1?w=1920&h=1080&fit=crop',
                'rating' => 9.8,
                'release_date' => '2024-11-01',
                'category' => 'VJ Translated',
                'media_type' => 'SERIES',
                'vj' => 'VJ Junior',
                'genres' => ['Mystery', 'Sci-Fi'],
                'access_type' => 'PREMIUM',
                'video_url' => self::TEST_MP4,
                'seasons' => [
                    [
                        'number' => 1,
                        'episodes' => [
                            [
                                'number' => 1,
                                'title' => 'Pilot',
                                'thumbnail' => 'https://picsum.photos/seed/s1e1/400/225',
                                'duration' => '45m',
                                'description' => 'The journey begins.',
                                'video_url' => self::TEST_MP4,
                            ],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Kampala Nights: Series',
                'description' => 'Weekly racing drama.',
                'thumbnail' => 'https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?w=400&h=600&fit=crop',
                'backdrop' => 'https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?w=1920&h=1080&fit=crop',
                'rating' => 8.5,
                'release_date' => '2024-05-15',
                'category' => 'VJ Translated',
                'media_type' => 'SERIES',
                'vj' => 'VJ Jingo',
                'genres' => ['Action', 'Crime'],
                'access_type' => 'RENT',
                'video_url' => self::TEST_MP4,
                'price_rent' => 1500,
            ],
            [
                'title' => 'Void Walkers',
                'description' => 'Interdimensional agents.',
                'thumbnail' => 'https://images.unsplash.com/photo-1451187580459-43490279c0fa?w=400&h=600&fit=crop',
                'backdrop' => 'https://images.unsplash.com/photo-1451187580459-43490279c0fa?w=1920&h=1080&fit=crop',
                'rating' => 9.0,
                'release_date' => '2024-01-20',
                'category' => 'Series',
                'media_type' => 'SERIES',
                'genres' => ['Sci-Fi', 'Action'],
                'access_type' => 'PREMIUM',
                'video_url' => self::TEST_MP4,
            ],
            [
                'title' => 'Bloodlines',
                'description' => 'Family feuds in history.',
                'thumbnail' => 'https://images.unsplash.com/photo-1533929736458-ca588d08c8be?w=400&h=600&fit=crop',
                'backdrop' => 'https://images.unsplash.com/photo-1533929736458-ca588d08c8be?w=1920&h=1080&fit=crop',
                'rating' => 8.4,
                'release_date' => '2023-09-15',
                'category' => 'Series',
                'media_type' => 'SERIES',
                'genres' => ['History', 'Drama'],
                'access_type' => 'FREE',
                'video_url' => self::TEST_MP4,
            ],
            [
                'title' => 'Neon Shadows',
                'description' => 'Cyber detective stories.',
                'thumbnail' => 'https://images.unsplash.com/photo-1550745165-9bc0b252726f?w=400&h=600&fit=crop',
                'backdrop' => 'https://images.unsplash.com/photo-1550745165-9bc0b252726f?w=1920&h=1080&fit=crop',
                'rating' => 8.7,
                'release_date' => '2024-03-10',
                'category' => 'Series',
                'media_type' => 'SERIES',
                'vj' => 'VJ Mark',
                'genres' => ['Sci-Fi', 'Crime'],
                'access_type' => 'RENT',
                'video_url' => self::TEST_MP4,
                'price_rent' => 2000,
            ],
            [
                'title' => 'The Last Oracle',
                'description' => 'Fantasy epic.',
                'thumbnail' => 'https://images.unsplash.com/photo-1514539079130-25950c84af65?w=400&h=600&fit=crop',
                'backdrop' => 'https://images.unsplash.com/photo-1514539079130-25950c84af65?w=1920&h=1080&fit=crop',
                'rating' => 9.5,
                'release_date' => '2024-07-25',
                'category' => 'Series',
                'media_type' => 'SERIES',
                'genres' => ['Fantasy', 'Adventure'],
                'access_type' => 'PREMIUM',
                'video_url' => self::TEST_MP4,
            ],
            [
                'title' => 'Concrete Jungle',
                'description' => 'Modern day survival.',
                'thumbnail' => 'https://images.unsplash.com/photo-1449824913935-59a10b8d2000?w=400&h=600&fit=crop',
                'backdrop' => 'https://images.unsplash.com/photo-1449824913935-59a10b8d2000?w=1920&h=1080&fit=crop',
                'rating' => 7.6,
                'release_date' => '2024-02-05',
                'category' => 'Series',
                'media_type' => 'SERIES',
                'genres' => ['Drama', 'Thriller'],
                'access_type' => 'FREE',
                'video_url' => self::TEST_MP4,
            ],
            [
                'title' => 'Pulse Rate',
                'description' => 'Medical thriller.',
                'thumbnail' => 'https://images.unsplash.com/photo-1505751172876-fa1923c5c528?w=400&h=600&fit=crop',
                'backdrop' => 'https://images.unsplash.com/photo-1505751172876-fa1923c5c528?w=1920&h=1080&fit=crop',
                'rating' => 8.1,
                'release_date' => '2024-08-18',
                'category' => 'Series',
                'media_type' => 'SERIES',
                'vj' => 'VJ Jingo',
                'genres' => ['Medical', 'Drama'],
                'access_type' => 'RENT',
                'video_url' => self::TEST_MP4,
                'price_rent' => 2000,
            ],
            [
                'title' => 'Stellar Wind',
                'description' => 'Space colonies.',
                'thumbnail' => 'https://images.unsplash.com/photo-1451187580459-43490279c0fa?w=400&h=600&fit=crop',
                'backdrop' => 'https://images.unsplash.com/photo-1451187580459-43490279c0fa?w=1920&h=1080&fit=crop',
                'rating' => 8.8,
                'release_date' => '2023-12-01',
                'category' => 'Series',
                'media_type' => 'SERIES',
                'genres' => ['Sci-Fi'],
                'access_type' => 'PREMIUM',
                'video_url' => self::TEST_MP4,
            ],
            [
                'title' => 'Urban Legend',
                'description' => 'Modern myths.',
                'thumbnail' => 'https://images.unsplash.com/photo-1478720568477-152d9b164e26?w=400&h=600&fit=crop',
                'backdrop' => 'https://images.unsplash.com/photo-1478720568477-152d9b164e26?w=1920&h=1080&fit=crop',
                'rating' => 7.9,
                'release_date' => '2024-10-12',
                'category' => 'Series',
                'media_type' => 'SERIES',
                'vj' => 'VJ Mark',
                'genres' => ['Mystery', 'Horror'],
                'access_type' => 'FREE',
                'video_url' => self::TEST_MP4,
            ],
        ];

        // Create movies
        foreach ($movies as $movieData) {
            $this->createMedia($movieData, $categories, $genres, $vjs, $actors);
        }

        // Create series
        foreach ($series as $seriesData) {
            $this->createMedia($seriesData, $categories, $genres, $vjs, $actors, true);
        }
    }

    private function createMedia(array $data, $categories, $genres, $vjs, $actors, $isSeries = false): void
    {
        $seasonsData = $data['seasons'] ?? null;
        unset($data['seasons']);

        $movieGenres = $data['genres'] ?? [];
        unset($data['genres']);

        $vjName = $data['vj'] ?? null;
        unset($data['vj']);

        $categoryName = $data['category'];
        unset($data['category']); // Remove category name, we'll use category_id
        $category = $categories[$categoryName] ?? $categories->first();
        $data['category_id'] = $category->id;

        if ($vjName && isset($vjs[$vjName])) {
            $data['vj_id'] = $vjs[$vjName]->id;
        }

        // Check if movie exists by slug
        $slug = \Illuminate\Support\Str::slug($data['title']);
        $movie = Movie::firstOrCreate(['slug' => $slug], array_merge($data, ['slug' => $slug]));

        // Attach genres (use syncWithoutDetaching to avoid duplicates)
        $genreIds = [];
        foreach ($movieGenres as $genreName) {
            if (isset($genres[$genreName])) {
                $genreIds[] = $genres[$genreName]->id;
            }
        }
        if (!empty($genreIds)) {
            $movie->genres()->syncWithoutDetaching($genreIds);
        }

        // Attach some actors (only if not already attached)
        $randomActors = $actors->random(min(3, $actors->count()));
        $order = 0;
        foreach ($randomActors as $actor) {
            if (!$movie->actors()->where('actors.id', $actor->id)->exists()) {
                $movie->actors()->attach($actor->id, [
                    'role' => 'Character ' . ($order + 1),
                    'order' => $order++,
                ]);
            }
        }

        // Create seasons and episodes for series (only if not exists)
        if ($isSeries && $seasonsData) {
            foreach ($seasonsData as $seasonData) {
                $episodesData = $seasonData['episodes'] ?? [];
                unset($seasonData['episodes']);

                $season = Season::firstOrCreate(
                    ['media_id' => $movie->id, 'number' => $seasonData['number']],
                    [
                        'media_id' => $movie->id,
                        'number' => $seasonData['number'],
                        'title' => $seasonData['title'] ?? null,
                        'description' => $seasonData['description'] ?? null,
                    ]
                );

                foreach ($episodesData as $episodeData) {
                    Episode::firstOrCreate(
                        ['season_id' => $season->id, 'number' => $episodeData['number']],
                        [
                            'season_id' => $season->id,
                            'number' => $episodeData['number'],
                            'title' => $episodeData['title'],
                            'thumbnail' => $episodeData['thumbnail'] ?? null,
                            'duration' => $episodeData['duration'] ?? null,
                            'description' => $episodeData['description'] ?? null,
                            'video_url' => $episodeData['video_url'] ?? null,
                        ]
                    );
                }
            }
        }
    }
}
