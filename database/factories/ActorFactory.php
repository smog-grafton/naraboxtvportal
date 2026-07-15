<?php

namespace Database\Factories;

use App\Models\Actor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Actor>
 */
class ActorFactory extends Factory
{
    protected $model = Actor::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->name(),
            'bio' => fake()->sentence(),
        ];
    }
}
