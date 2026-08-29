<?php

namespace Tests\Feature;

use App\Models\Movie;
use App\Models\TVShow;
use App\Models\VJ;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VjArchiveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['api.enabled' => false]);
    }

    public function test_vj_movie_archive_returns_only_published_attributed_works(): void
    {
        $vj = VJ::factory()->create(['slug' => 'vj-mark']);
        $published = Movie::factory()->create(['vj_id' => $vj->id, 'title' => 'Motor City']);
        Movie::factory()->create(['vj_id' => $vj->id, 'content_status' => 'dmca_removed']);
        Movie::factory()->create();

        $this->getJson('/api/v1/movies?vj=vj-mark&sort=title&order=asc')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $published->id)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_vj_tv_archive_uses_the_canonical_tv_show_relationship(): void
    {
        $vj = VJ::factory()->create();
        $published = TVShow::factory()->create(['vj_id' => $vj->id]);
        TVShow::factory()->create();

        $this->getJson("/api/v1/tv-shows?vj_id={$vj->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $published->id)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_vj_tv_archive_includes_legacy_series_stored_in_the_movies_table(): void
    {
        $vj = VJ::factory()->create();
        $canonical = TVShow::factory()->create(['vj_id' => $vj->id]);
        $legacySeries = Movie::factory()->create([
            'vj_id' => $vj->id,
            'media_type' => 'SERIES',
            'title' => 'Kampala Nights: Series',
        ]);
        // Unrelated content must not leak in.
        Movie::factory()->create(['media_type' => 'SERIES']);
        Movie::factory()->create(['vj_id' => $vj->id, 'media_type' => 'MOVIE']);

        $response = $this->getJson("/api/v1/tv-shows?vj_id={$vj->id}")
            ->assertOk()
            ->assertJsonPath('meta.total', 2);

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($canonical->id, $ids);
        $this->assertContains($legacySeries->id, $ids);
    }
}
