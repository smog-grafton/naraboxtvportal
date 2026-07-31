<?php

namespace Tests\Feature;

use App\Models\HeroSlide;
use App\Models\Movie;
use App\Models\Subscription;
use App\Models\TVShow;
use App\Models\User;
use App\Services\MediaAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TVShowIdentityAndAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_hero_resolves_legacy_series_mirror_to_canonical_tv_show(): void
    {
        $slug = 'el-chapo-vj-mark';
        $mirror = Movie::factory()->create([
            'slug' => $slug,
            'title' => 'El Chapo - VJ Mark',
            'media_type' => 'SERIES',
        ]);
        $show = TVShow::factory()->create([
            'slug' => $slug,
            'title' => 'El Chapo - VJ Mark',
        ]);
        HeroSlide::query()->create([
            'media_id' => $mirror->id,
            'order' => 1,
            'is_active' => true,
        ]);

        $response = $this->withHeader('X-API-KEY', (string) config('api.key'))
            ->getJson('/api/v1/hero');

        $response->assertOk()
            ->assertJsonPath('data.0.id', $show->id)
            ->assertJsonPath('data.0.media_id', $show->id)
            ->assertJsonPath('data.0.slug', $slug)
            ->assertJsonPath('data.0.mediaType', 'TV_SHOW')
            ->assertJsonPath('data.0.media_type', 'TV_SHOW');
    }

    public function test_published_tv_show_detail_is_public_without_app_key(): void
    {
        config(['api.enabled' => true, 'api.key' => 'rotated-app-key']);
        $show = TVShow::factory()->create(['slug' => 'public-series']);

        $this->getJson('/api/v1/tv-shows/public-series')
            ->assertOk()
            ->assertJsonPath('id', $show->id)
            ->assertJsonPath('slug', 'public-series');
    }

    public function test_legacy_active_subscription_still_grants_premium_playback_access(): void
    {
        $user = User::factory()->create();
        $show = TVShow::factory()->create([
            'is_free' => false,
            'is_premium' => true,
        ]);
        Subscription::query()->create([
            'user_id' => $user->id,
            'plan' => 'PRO',
            'status' => 'ACTIVE',
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'renewal_date' => now()->addMonth()->toDateString(),
            'amount' => 10000,
        ]);

        $access = app(MediaAccessService::class)->evaluate($show, $user);

        $this->assertTrue($access['has_access']);
        $this->assertSame('SUBSCRIPTION', $access['access_type']);
        $this->assertSame('ACCESS_GRANTED', $access['code']);
        $this->assertSame(200, $access['http_status']);
    }

    public function test_locked_access_decision_is_a_successful_http_response_for_released_apps(): void
    {
        $user = User::factory()->create();
        $movie = Movie::factory()->create([
            'is_free' => false,
            'is_premium' => true,
        ]);
        Sanctum::actingAs($user);

        $this->withHeader('X-API-KEY', (string) config('api.key'))
            ->postJson('/api/v1/access/check', [
            'media_id' => $movie->id,
            'media_type' => 'MOVIE',
            ])->assertOk()
            ->assertJsonPath('has_access', false)
            ->assertJsonPath('access_type', 'PREMIUM')
            ->assertJsonPath('requires_subscription', true);
    }

    public function test_manual_admin_plan_without_ledger_history_grants_premium_access(): void
    {
        $user = User::factory()->create([
            'plan' => 'ELITE',
            'plan_status' => 'ACTIVE',
            'renewal_date' => null,
        ]);
        $show = TVShow::factory()->create([
            'is_free' => false,
            'is_premium' => true,
        ]);

        $access = app(MediaAccessService::class)->evaluate($show, $user);

        $this->assertTrue($access['has_access']);
        $this->assertSame('SUBSCRIPTION', $access['access_type']);
    }

    public function test_profile_and_dashboard_preserve_manual_admin_plan(): void
    {
        $user = User::factory()->create([
            'plan' => 'ELITE',
            'plan_status' => 'ACTIVE',
            'renewal_date' => null,
        ]);
        Sanctum::actingAs($user);
        $headers = ['X-API-KEY' => (string) config('api.key')];

        $this->withHeaders($headers)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.planStatus', 'ACTIVE')
            ->assertJsonPath('data.plan', 'ELITE');

        $this->withHeaders($headers)
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('user.planStatus', 'ACTIVE')
            ->assertJsonPath('user.plan', 'ELITE');

        $this->assertSame('ACTIVE', $user->fresh()->plan_status);
    }

    public function test_lifecycle_backfill_publishes_only_existing_administrator_content(): void
    {
        $adminMovie = Movie::factory()->create([
            'content_status' => 'published',
            'submission_origin' => 'administrator',
            'publication_status' => 'draft',
            'editorial_status' => 'not_submitted',
            'publish_status' => 'draft',
        ]);
        $creatorMovie = Movie::factory()->create([
            'content_status' => 'published',
            'submission_origin' => 'creator',
            'publication_status' => 'draft',
            'editorial_status' => 'not_submitted',
            'publish_status' => 'draft',
        ]);

        $migration = require database_path('migrations/2026_07_31_220500_backfill_admin_published_content_lifecycle.php');
        $migration->up();

        $this->assertSame('published', $adminMovie->fresh()->publication_status);
        $this->assertSame('approved', $adminMovie->fresh()->editorial_status);
        $this->assertSame('published', $adminMovie->fresh()->publish_status);
        $this->assertSame('draft', $creatorMovie->fresh()->publication_status);
        $this->assertSame('not_submitted', $creatorMovie->fresh()->editorial_status);
    }
}
