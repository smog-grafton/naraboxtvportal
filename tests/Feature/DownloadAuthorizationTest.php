<?php

namespace Tests\Feature;

use App\Models\DownloadSource;
use App\Models\Movie;
use App\Models\SubscriptionPlan;
use App\Models\TVShow;
use App\Models\User;
use App\Models\UserPurchase;
use App\Models\UserRental;
use App\Models\UserSubscription;
use App\Services\ContaboObjectStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class DownloadAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_receives_structured_sign_in_requirement_for_paid_download(): void
    {
        $source = $this->downloadSource([
            'is_free' => false,
            'is_premium' => false,
            'price_buy' => 12000,
        ]);

        $this->postJson("/api/v1/downloads/{$source->id}/authorize")
            ->assertUnauthorized()
            ->assertJsonPath('code', 'AUTHENTICATION_REQUIRED')
            ->assertJsonPath('requires_auth', true)
            ->assertJsonPath('message', 'Sign in again, then request the download from the title page.');
    }

    public function test_entitled_user_receives_short_lived_signed_url_without_access_token(): void
    {
        $source = $this->downloadSource([
            'is_free' => false,
            'is_premium' => false,
            'price_buy' => 12000,
        ]);
        $user = User::factory()->create();
        UserPurchase::query()->create([
            'user_id' => $user->id,
            'purchasable_type' => Movie::class,
            'purchasable_id' => $source->downloadable_id,
            'purchased_at' => now(),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/downloads/{$source->id}/authorize")
            ->assertOk()
            ->assertJsonStructure(['download_url', 'expires_at']);

        $downloadUrl = (string) $response->json('download_url');
        $this->assertStringContainsString('signature=', $downloadUrl);
        $this->assertStringNotContainsString('access_token=', $downloadUrl);
        $this->assertTrue(app('url')->hasValidSignature(Request::create($downloadUrl)));
    }

    public function test_free_download_authorization_remains_backward_compatible_for_guests(): void
    {
        $source = $this->downloadSource([
            'is_free' => true,
            'is_premium' => false,
        ]);

        $this->postJson("/api/v1/downloads/{$source->id}/authorize")
            ->assertOk()
            ->assertJsonStructure(['download_url', 'expires_at']);
    }

    public function test_contabo_download_uses_short_lived_direct_object_delivery(): void
    {
        $source = $this->downloadSource([
            'is_free' => true,
            'is_premium' => false,
        ]);
        $source->update([
            'url' => 'https://usc1.contabostorage.com/tenant:nbx/videos/movie.mp4',
        ]);

        $contabo = Mockery::mock(ContaboObjectStorageService::class);
        $contabo->shouldReceive('isContaboPublicUrl')->once()->andReturnTrue();
        $contabo->shouldReceive('objectKeyFromPublicUrl')->once()->andReturn('videos/movie.mp4');
        $contabo->shouldReceive('isConfigured')->once()->andReturnTrue();
        $contabo->shouldReceive('diskName')->once()->andReturn('contabo');
        $this->app->instance(ContaboObjectStorageService::class, $contabo);

        $disk = Mockery::mock();
        $disk->shouldReceive('temporaryUrl')
            ->once()
            ->with(
                'videos/movie.mp4',
                Mockery::type(\DateTimeInterface::class),
                Mockery::on(fn (array $options): bool => str_contains(
                    (string) ($options['ResponseContentDisposition'] ?? ''),
                    'attachment;'
                ))
            )
            ->andReturn('https://usc1.contabostorage.com/tenant:nbx/videos/movie.mp4?X-Amz-Signature=test');
        Storage::shouldReceive('disk')->once()->with('contabo')->andReturn($disk);

        $this->postJson("/api/v1/downloads/{$source->id}/authorize")
            ->assertOk()
            ->assertJsonPath(
                'download_url',
                'https://usc1.contabostorage.com/tenant:nbx/videos/movie.mp4?X-Amz-Signature=test'
            );
    }

    public function test_active_rental_unlocks_a_paid_download(): void
    {
        $source = $this->downloadSource([
            'is_free' => false,
            'price_rent' => 5000,
        ]);
        $user = User::factory()->create();
        UserRental::query()->create([
            'user_id' => $user->id,
            'rentable_type' => Movie::class,
            'rentable_id' => $source->downloadable_id,
            'rented_at' => now()->subDay(),
            'expires_at' => now()->addDays(29),
            'is_active' => true,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/downloads/{$source->id}/authorize")
            ->assertOk()
            ->assertJsonStructure(['download_url', 'expires_at']);
    }

    public function test_active_subscription_unlocks_a_premium_download(): void
    {
        $source = $this->downloadSource([
            'is_free' => false,
            'is_premium' => true,
        ]);
        $user = User::factory()->create();
        $plan = SubscriptionPlan::query()->create([
            'name' => 'Monthly',
            'slug' => 'monthly-test',
            'duration_days' => 30,
            'price' => 10000,
            'is_active' => true,
        ]);
        UserSubscription::query()->create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'started_at' => now()->subDay(),
            'expires_at' => now()->addDays(29),
            'status' => 'ACTIVE',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/downloads/{$source->id}/authorize")
            ->assertOk()
            ->assertJsonStructure(['download_url', 'expires_at']);
    }

    public function test_episode_download_uses_the_parent_tv_show_entitlement(): void
    {
        $show = TVShow::factory()->create([
            'is_free' => false,
            'is_premium' => false,
            'price_buy' => 18000,
            'download_enabled' => true,
        ]);
        $season = $show->seasons()->create([
            'number' => 1,
            'title' => 'Season 1',
        ]);
        $episode = $season->episodes()->create([
            'number' => 1,
            'title' => 'Pilot',
            'download_enabled' => true,
        ]);
        $source = $episode->downloadSources()->create([
            'type' => 'url',
            'url' => 'https://objects.example/videos/episode.mp4',
            'quality' => '480p',
            'format' => 'mp4',
            'label' => '480p MP4',
            'is_active' => true,
        ]);
        $user = User::factory()->create();
        UserPurchase::query()->create([
            'user_id' => $user->id,
            'purchasable_type' => TVShow::class,
            'purchasable_id' => $show->id,
            'purchased_at' => now(),
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/downloads/{$source->id}/authorize")
            ->assertOk()
            ->assertJsonStructure(['download_url', 'expires_at']);
    }

    public function test_expired_rental_returns_a_stable_entitlement_code(): void
    {
        $source = $this->downloadSource([
            'is_free' => false,
            'price_rent' => 5000,
        ]);
        $user = User::factory()->create();
        UserRental::query()->create([
            'user_id' => $user->id,
            'rentable_type' => Movie::class,
            'rentable_id' => $source->downloadable_id,
            'rented_at' => now()->subDays(31),
            'expires_at' => now()->subDay(),
            'is_active' => false,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/downloads/{$source->id}/authorize")
            ->assertForbidden()
            ->assertJsonPath('code', 'ENTITLEMENT_EXPIRED')
            ->assertJsonPath('requires_payment', true);
    }

    public function test_user_without_entitlement_receives_a_stable_purchase_code(): void
    {
        $source = $this->downloadSource([
            'is_free' => false,
            'price_buy' => 12000,
        ]);
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/downloads/{$source->id}/authorize")
            ->assertForbidden()
            ->assertJsonPath('code', 'PURCHASE_REQUIRED')
            ->assertJsonPath('requires_payment', true);
    }

    public function test_unreachable_remote_source_returns_a_safe_retryable_error(): void
    {
        $source = $this->downloadSource([
            'is_free' => true,
            'is_premium' => false,
        ]);
        $source->update(['url' => 'http://127.0.0.1:9/private/source.mp4']);

        $authorization = $this->postJson("/api/v1/downloads/{$source->id}/authorize")
            ->assertOk();

        $this->getJson((string) $authorization->json('download_url'))
            ->assertStatus(502)
            ->assertJsonPath('code', 'DOWNLOAD_SOURCE_UNAVAILABLE')
            ->assertJsonPath('retryable', true)
            ->assertJsonMissingPath('source_url')
            ->assertJsonMissing(['Connection refused']);
    }

    public function test_long_lived_access_tokens_are_not_accepted_in_download_urls(): void
    {
        $source = $this->downloadSource([
            'is_free' => false,
            'is_premium' => false,
            'price_buy' => 12000,
        ]);
        $user = User::factory()->create();
        UserPurchase::query()->create([
            'user_id' => $user->id,
            'purchasable_type' => Movie::class,
            'purchasable_id' => $source->downloadable_id,
            'purchased_at' => now(),
        ]);

        $token = $user->createToken('legacy-download-link')->plainTextToken;

        $this->getJson("/api/v1/downloads/{$source->id}?access_token=".urlencode($token))
            ->assertUnauthorized()
            ->assertJsonPath('requires_auth', true);
    }

    private function downloadSource(array $movieAttributes): DownloadSource
    {
        $movie = Movie::factory()->create(array_merge([
            'download_enabled' => true,
        ], $movieAttributes));

        return $movie->downloadSources()->create([
            'type' => 'url',
            'url' => 'https://objects.example/videos/movie.mp4',
            'quality' => '480p',
            'format' => 'mp4',
            'label' => '480p MP4',
            'is_active' => true,
        ]);
    }
}
