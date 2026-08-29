<?php

namespace Tests\Feature;

use App\Models\SubscriptionPlan;
use App\Models\TvDeviceCode;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TvAccountExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_pairing_code_returns_explicit_expired_state(): void
    {
        $headers = ['X-API-KEY' => (string) config('api.key')];
        $issued = $this->withHeaders($headers)
            ->postJson('/api/v1/tv/auth/device-code')
            ->assertOk()
            ->assertJsonStructure(['data' => ['device_code', 'qr_payload', 'expires_at', 'interval']]);

        $deviceCode = $issued->json('data.device_code');
        TvDeviceCode::query()->firstOrFail()->update(['expires_at' => now()->subSecond()]);

        $this->withHeaders($headers)
            ->postJson('/api/v1/tv/auth/device-code/poll', ['device_code' => $deviceCode])
            ->assertStatus(410)
            ->assertJsonPath('data.status', TvDeviceCode::STATUS_EXPIRED);
    }

    public function test_pairing_can_be_confirmed_against_the_expected_tv_and_returns_a_refreshable_session(): void
    {
        $headers = ['X-API-KEY' => (string) config('api.key')];
        $issued = $this->withHeaders($headers)->postJson('/api/v1/tv/auth/device-code', [
            'device_id' => 'living-room-tv',
            'device_name' => 'Living Room TV',
            'platform' => 'android-tv',
        ])->assertOk();

        $this->withHeaders($headers)
            ->postJson('/api/v1/tv/auth/device-code/inspect', [
                'user_code' => $issued->json('data.user_code'),
            ])
            ->assertOk()
            ->assertJsonPath('data.status', TvDeviceCode::STATUS_PENDING)
            ->assertJsonPath('data.device.name', 'Living Room TV');

        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->withHeaders($headers)
            ->postJson('/api/v1/tv/auth/device-code/activate', [
                'user_code' => $issued->json('data.user_code'),
            ])
            ->assertOk();

        $this->withHeaders($headers)
            ->postJson('/api/v1/tv/auth/device-code/poll', [
                'device_code' => $issued->json('data.device_code'),
            ])
            ->assertOk()
            ->assertJsonPath('data.status', TvDeviceCode::STATUS_APPROVED)
            ->assertJsonStructure(['data' => ['token', 'refresh_token', 'access_token_expires_at']]);
    }

    public function test_dashboard_preserves_latest_expired_subscription_for_truthful_profile_state(): void
    {
        $user = User::factory()->create([
            'plan' => 'FREE',
            'plan_status' => 'NONE',
            'renewal_date' => null,
        ]);
        $plan = SubscriptionPlan::query()->create([
            'name' => 'Monthly Access',
            'slug' => 'monthly-access-test',
            'duration_days' => 30,
            'price' => 8500,
            'is_active' => true,
        ]);
        $expiredAt = now()->subDay()->startOfSecond();
        UserSubscription::query()->create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'started_at' => $expiredAt->copy()->subDays(30),
            'expires_at' => $expiredAt,
            'status' => 'EXPIRED',
        ]);
        Sanctum::actingAs($user);

        $this->withHeader('X-API-KEY', (string) config('api.key'))
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('subscription.plan', 'Monthly Access')
            ->assertJsonPath('subscription.status', 'EXPIRED')
            ->assertJsonPath('subscription.expires_at', $expiredAt->toIso8601String())
            ->assertJsonPath('user.planStatus', 'NONE');
    }
}
