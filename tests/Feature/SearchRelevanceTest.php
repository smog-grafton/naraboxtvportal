<?php

namespace Tests\Feature;

use App\Models\Movie;
use App\Models\TVShow;
use App\Models\VJ;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchRelevanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['api.enabled' => false]);
    }

    public function test_partial_title_match_beats_popular_incidental_matches(): void
    {
        $expected = Movie::factory()->create([
            'title' => 'Motor City',
            'description' => 'A crime story.',
            'trending_score' => 1,
            'views_count' => 1,
            'rating' => 4.0,
        ]);

        Movie::factory()->create([
            'title' => 'Fastest Machines Alive',
            'description' => 'A motor documentary.',
            'trending_score' => 999_999,
            'views_count' => 999_999,
            'rating' => 10.0,
        ]);

        $this->getJson('/api/v1/search?q=motor')
            ->assertOk()
            ->assertJsonPath('data.archives.0.id', (string) $expected->id)
            ->assertJsonPath('data.archives.0.title', 'Motor City');
    }

    public function test_hidden_catalogue_items_do_not_leak_into_search(): void
    {
        Movie::factory()->create([
            'title' => 'Motor City Removed',
            'content_status' => 'dmca_removed',
        ]);

        $this->getJson('/api/v1/search?q=motor')
            ->assertOk()
            ->assertJsonCount(0, 'data.archives');
    }

    public function test_conservative_typo_tolerance_recovers_a_multi_word_title(): void
    {
        $expected = Movie::factory()->create([
            'title' => 'Motor City',
            'description' => 'A crime story.',
            'trending_score' => 0,
        ]);

        Movie::factory()->create([
            'title' => 'City of Glass',
            'description' => 'A different city story.',
            'trending_score' => 999_999,
        ]);

        $this->getJson('/api/v1/search?q=motro%20city')
            ->assertOk()
            ->assertJsonPath('data.archives.0.id', (string) $expected->id)
            ->assertJsonPath('data.archives.0.title', 'Motor City');
    }

    public function test_exact_title_beats_prefix_and_contains_title_matches(): void
    {
        $exact = Movie::factory()->create(['title' => 'Motor', 'trending_score' => 0]);
        $prefix = Movie::factory()->create(['title' => 'Motor City', 'trending_score' => 999]);
        $contains = Movie::factory()->create(['title' => 'The Motor Story', 'trending_score' => 999_999]);

        $response = $this->getJson('/api/v1/search?q=motor')->assertOk();
        $ids = collect($response->json('data.archives'))->pluck('id')->all();

        $this->assertSame([(string) $exact->id, (string) $prefix->id, (string) $contains->id], array_slice($ids, 0, 3));
    }

    public function test_vj_and_tv_show_results_use_the_same_canonical_search_endpoint(): void
    {
        $vj = VJ::factory()->create(['name' => 'VJ Stubborn']);
        $show = TVShow::factory()->create(['title' => 'Motor City Files']);

        $this->getJson('/api/v1/search?q=stubborn')
            ->assertOk()
            ->assertJsonPath('data.people.0.id', (string) $vj->id);

        $this->getJson('/api/v1/search?q=motor')
            ->assertOk()
            ->assertJsonPath('data.archives.0.id', (string) $show->id)
            ->assertJsonPath('data.archives.0.media_type', 'TV_SHOW');
    }
}
