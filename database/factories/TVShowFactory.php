<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\TVShow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TVShow>
 */
class TVShowFactory extends Factory
{
    protected $model = TVShow::class;

    public function definition(): array
    {
        return [
            'title' => fake()->unique()->sentence(3),
            'description' => fake()->paragraph(),
            'thumbnail' => 'shows/placeholder-thumb.jpg',
            'backdrop' => 'shows/placeholder-backdrop.jpg',
            'rating' => fake()->randomFloat(1, 0, 10),
            'release_date' => fake()->date(),
            'category_id' => Category::factory(),
            'is_active' => true,
            'content_status' => 'published',
            'views_count' => fake()->numberBetween(0, 100000),
            'manual_views' => 0,
        ];
    }
}
