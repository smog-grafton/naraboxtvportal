<?php

namespace Tests\Feature;

use App\Models\Movie;
use App\Models\PlaybackSession;
use App\Models\TVShow;
use App\Models\VJ;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class TrendingApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-08-21 12:00:00 UTC');
        Cache::clear();
        $this->withHeader(config('api.header', 'X-API-KEY'), (string) config('api.key'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_recent_qualified_playback_ranks_above_lifetime_view_fallback(): void
    {
        $recent = Movie::factory()->create(['views_count' => 1, 'manual_views' => 0]);
        Movie::factory()->create(['views_count' => 999999, 'manual_views' => 0]);

        $this->createPlaybackSession($recent->id, 'MOVIE', 600, now()->subHour());

        $this->getJson('/api/v1/trending?type=movie&period=today&limit=2')
            ->assertOk()
            ->assertJsonPath('data.0.id', $recent->id)
            ->assertJsonPath('data.0.trending.sessions', 1)
            ->assertJsonPath('meta.period', 'today')
            ->assertJsonPath('meta.timezone', 'Africa/Kampala');
    }

    public function test_now_is_a_rolling_six_hour_window_and_ignores_short_sessions(): void
    {
        $recent = Movie::factory()->create(['views_count' => 0]);
        $old = Movie::factory()->create(['views_count' => 100]);
        $short = Movie::factory()->create(['views_count' => 50]);

        $this->createPlaybackSession($recent->id, 'MOVIE', 120, now()->subHours(2));
        $this->createPlaybackSession($old->id, 'MOVIE', 1200, now()->subHours(7));
        $this->createPlaybackSession($short->id, 'MOVIE', 10, now()->subMinutes(10));

        $response = $this->getJson('/api/v1/trending?type=movie&period=now&limit=3')
            ->assertOk();

        $response->assertJsonPath('data.0.id', $recent->id);
        $this->assertSame(0, collect($response->json('data'))->firstWhere('id', $short->id)['trending']['sessions']);
        $this->assertSame(0, collect($response->json('data'))->firstWhere('id', $old->id)['trending']['sessions']);
    }

    public function test_type_and_vj_scope_are_enforced_server_side(): void
    {
        $vj = VJ::factory()->create();
        $otherVj = VJ::factory()->create();
        $scoped = TVShow::factory()->create(['vj_id' => $vj->id, 'views_count' => 1]);
        $other = TVShow::factory()->create(['vj_id' => $otherVj->id, 'views_count' => 5000]);
        $movie = Movie::factory()->create(['vj_id' => $vj->id, 'views_count' => 9000]);

        $this->createPlaybackSession($scoped->id, 'TV_SHOW', 300, now()->subDay());
        $this->createPlaybackSession($other->id, 'TV_SHOW', 900, now()->subDay());
        $this->createPlaybackSession($movie->id, 'MOVIE', 1200, now()->subDay());

        $response = $this->getJson("/api/v1/trending?type=tv_show&period=week&vj_id={$vj->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $scoped->id)
            ->assertJsonPath('meta.vj_id', $vj->id);

        $this->assertNotContains($other->id, collect($response->json('data'))->pluck('id')->all());
    }

    public function test_max_position_qualifies_a_session_when_reported_watch_time_is_zero(): void
    {
        $played = Movie::factory()->create(['views_count' => 0]);
        Movie::factory()->create(['views_count' => 5000]);

        PlaybackSession::query()->create([
            'session_uuid' => (string) Str::uuid(),
            'media_id' => $played->id,
            'media_type' => 'MOVIE',
            'total_watch_seconds' => 0,
            'max_position_seconds' => 180,
            'started_at' => now()->subHour(),
            'last_ping_at' => now()->subHour(),
        ]);

        $this->getJson('/api/v1/trending?type=movie&period=now&limit=2')
            ->assertOk()
            ->assertJsonPath('data.0.id', $played->id)
            ->assertJsonPath('data.0.trending.sessions', 1)
            ->assertJsonPath('data.0.trending.watch_seconds', 180);
    }

    public function test_invalid_contract_values_return_validation_errors(): void
    {
        $this->getJson('/api/v1/trending?type=series&period=forever&limit=100')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type', 'period', 'limit']);
    }

    private function createPlaybackSession(int $mediaId, string $mediaType, int $watchSeconds, $startedAt): PlaybackSession
    {
        return PlaybackSession::query()->create([
            'session_uuid' => (string) Str::uuid(),
            'media_id' => $mediaId,
            'media_type' => $mediaType,
            'total_watch_seconds' => $watchSeconds,
            'max_position_seconds' => $watchSeconds,
            'started_at' => $startedAt,
            'last_ping_at' => $startedAt,
        ]);
    }
}
