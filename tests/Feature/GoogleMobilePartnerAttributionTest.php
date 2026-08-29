<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\PartnerAttribution;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class GoogleMobilePartnerAttributionTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_google_registration_can_select_an_optional_partner(): void
    {
        config(['api.enabled' => false]);
        $role = Role::query()->create(['name' => 'customer', 'display_name' => 'Customer']);
        $partner = Partner::query()->create([
            'user_id' => User::factory()->create(['role_id' => $role->id])->id,
            'display_name' => 'Movie Clips Uganda',
            'slug' => 'movie-clips-uganda',
            'referral_code' => 'CLIPSUG',
            'status' => 'active',
            'agreement_start_at' => now()->subDay(),
            'agreement_end_at' => now()->addMonth(),
        ]);

        $googleUser = Mockery::mock(SocialiteUser::class);
        $googleUser->shouldReceive('getEmail')->andReturn('new-google-user@example.com');
        $googleUser->shouldReceive('getId')->andReturn('google-partner-selection');
        $googleUser->shouldReceive('getName')->andReturn('Google Partner User');
        $googleUser->shouldReceive('getAvatar')->andReturn(null);

        $provider = Mockery::mock();
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('userFromToken')->with('google-token')->andReturn($googleUser);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->postJson('/api/v1/auth/google/mobile', [
            'access_token' => 'google-token',
            'partner_referral_code' => $partner->referral_code,
            'discovery_source' => 'partner_selection',
        ]);

        $response->assertOk()->assertJsonPath('data.is_new_user', true);
        $newUserId = (int) $response->json('data.user.id');

        $this->assertTrue(PartnerAttribution::query()
            ->where('user_id', $newUserId)
            ->where('partner_id', $partner->id)
            ->where('attribution_method', 'registration_selection')
            ->exists());
    }
}
