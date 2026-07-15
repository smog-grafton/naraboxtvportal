<?php

namespace Tests\Feature;

use App\Models\Genre;
use App\Models\Movie;
use App\Models\TVShow;
use App\Models\VJ;
use App\Services\SimilarTitlesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimilarTitlesServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_ranks_genre_and_vj_matches_above_genre_only_and_excludes_unrelated_when_pool_is_sufficient(): void
    {
        $genre = Genre::factory()->create();
        $otherGenre = Genre::factory()->create();
        $vj = VJ::factory()->create();
        $otherVj = VJ::factory()->create();

        $source = Movie::factory()->create(['vj_id' => $vj->id]);
        $source->genres()->attach($genre->id);

        $sameGenreSameVj = Movie::factory()->create(['vj_id' => $vj->id, 'views_count' => 10]);
        $sameGenreSameVj->genres()->attach($genre->id);

        $sameGenreOnly = Movie::factory()->create(['vj_id' => $otherVj->id, 'views_count' => 999999]);
        $sameGenreOnly->genres()->attach($genre->id);

        $unrelated = Movie::factory()->create(['views_count' => 999999]);
        $unrelated->genres()->attach($otherGenre->id);

        $results = app(SimilarTitlesService::class)->forMovie($source->fresh(), 2);

        $this->assertCount(2, $results);
        $this->assertSame($sameGenreSameVj->id, $results[0]->id);
        $this->assertSame($sameGenreOnly->id, $results[1]->id);
        $this->assertNotContains($unrelated->id, $results->pluck('id')->all());
    }

    public function test_falls_back_to_trending_when_genre_matches_are_insufficient(): void
    {
        $genre = Genre::factory()->create();

        $source = Movie::factory()->create();
        $source->genres()->attach($genre->id);

        $genreMatch = Movie::factory()->create(['views_count' => 5]);
        $genreMatch->genres()->attach($genre->id);

        $trendingA = Movie::factory()->create(['views_count' => 500]);
        $trendingB = Movie::factory()->create(['views_count' => 100]);

        $results = app(SimilarTitlesService::class)->forMovie($source->fresh(), 3);

        $this->assertCount(3, $results);
        // Genre match must lead even though it has far fewer views than the fallback titles.
        $this->assertSame($genreMatch->id, $results[0]->id);
        // Fallback slots are filled by trending order.
        $this->assertSame($trendingA->id, $results[1]->id);
        $this->assertSame($trendingB->id, $results[2]->id);
    }

    public function test_excludes_unpublished_and_inactive_titles(): void
    {
        $genre = Genre::factory()->create();

        $source = Movie::factory()->create();
        $source->genres()->attach($genre->id);

        $dmcaRemoved = Movie::factory()->create(['content_status' => 'dmca_removed']);
        $dmcaRemoved->genres()->attach($genre->id);

        $inactive = Movie::factory()->create(['is_active' => false]);
        $inactive->genres()->attach($genre->id);

        $results = app(SimilarTitlesService::class)->forMovie($source->fresh(), 10);

        $ids = $results->pluck('id')->all();
        $this->assertNotContains($dmcaRemoved->id, $ids);
        $this->assertNotContains($inactive->id, $ids);
    }

    public function test_legacy_series_movie_recommends_tv_shows_and_other_legacy_series_not_real_movies(): void
    {
        $genre = Genre::factory()->create();

        $source = Movie::factory()->create(['media_type' => 'SERIES']);
        $source->genres()->attach($genre->id);

        $otherLegacySeries = Movie::factory()->create(['media_type' => 'SERIES']);
        $otherLegacySeries->genres()->attach($genre->id);

        $dedicatedTvShow = TVShow::factory()->create();
        $dedicatedTvShow->genres()->attach($genre->id);

        $realMovie = Movie::factory()->create(['media_type' => 'MOVIE']);
        $realMovie->genres()->attach($genre->id);

        $results = app(SimilarTitlesService::class)->forMovie($source->fresh(), 10);

        $this->assertTrue($results->contains(fn ($item) => $item instanceof Movie && $item->id === $otherLegacySeries->id));
        $this->assertTrue($results->contains(fn ($item) => $item instanceof TVShow && $item->id === $dedicatedTvShow->id));
        $this->assertFalse($results->contains(fn ($item) => $item instanceof Movie && $item->id === $realMovie->id));
    }

    public function test_result_count_never_exceeds_limit_and_has_no_duplicates(): void
    {
        $genre = Genre::factory()->create();
        $source = Movie::factory()->create();
        $source->genres()->attach($genre->id);

        for ($i = 0; $i < 8; $i++) {
            $candidate = Movie::factory()->create(['views_count' => $i * 10]);
            $candidate->genres()->attach($genre->id);
        }

        $results = app(SimilarTitlesService::class)->forMovie($source->fresh(), 5);

        $this->assertCount(5, $results);
        $this->assertSame($results->pluck('id')->unique()->count(), $results->count());
    }
}
