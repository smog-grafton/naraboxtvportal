<?php

namespace Database\Seeders;

use App\Models\HeroSlide;
use App\Models\Movie;
use Illuminate\Database\Seeder;

class HeroSlideSeeder extends Seeder
{
    public function run(): void
    {
        // Get featured movies ordered by featured_order
        $featuredMovies = Movie::where('is_featured', true)
            ->whereNotNull('featured_order')
            ->orderBy('featured_order')
            ->get();

        $order = 1;
        foreach ($featuredMovies as $movie) {
            HeroSlide::firstOrCreate(
                ['media_id' => $movie->id],
                [
                    'media_id' => $movie->id,
                    'order' => $order++,
                    'is_active' => true,
                ]
            );
        }

        // If no featured movies, add first few movies
        if ($featuredMovies->isEmpty()) {
            $movies = Movie::where('is_active', true)
                ->orderBy('trending_score', 'desc')
                ->limit(5)
                ->get();

            $order = 1;
            foreach ($movies as $movie) {
                HeroSlide::firstOrCreate(
                    ['media_id' => $movie->id],
                    [
                        'media_id' => $movie->id,
                        'order' => $order++,
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
