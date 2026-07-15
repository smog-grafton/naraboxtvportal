<?php

namespace Tests\Feature\Api;

use App\Models\Genre;
use App\Models\TVShow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TvShowSimilarEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['api.enabled' => false]);
    }

    public function test_returns_similar_tv_shows_using_existing_summary_shape(): void
    {
        $genre = Genre::factory()->create();

        $source = TVShow::factory()->create();
        $source->genres()->attach($genre->id);

        $match = TVShow::factory()->create();
        $match->genres()->attach($genre->id);

        $response = $this->getJson("/api/v1/tv-shows/{$source->id}/similar");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'slug', 'title', 'genre', 'mediaType', 'is_free', 'is_premium', 'priceRent', 'priceBuy'],
            ],
        ]);
        $this->assertSame([$match->id], collect($response->json('data'))->pluck('id')->all());
    }

    public function test_returns_404_for_unknown_tv_show(): void
    {
        $response = $this->getJson('/api/v1/tv-shows/999999/similar');

        $response->assertStatus(404);
    }
}
