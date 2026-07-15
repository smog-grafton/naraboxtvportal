<?php

namespace Database\Factories;

use App\Models\VJ;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VJ>
 */
class VJFactory extends Factory
{
    protected $model = VJ::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->name(),
            'image' => 'vjs/placeholder.jpg',
            'rating' => fake()->randomFloat(1, 3, 5),
            'bio' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
