<?php

namespace Tests\Feature;

use App\Models\MaintenanceWindow;
use App\Models\PlatformVersionPolicy;
use App\Models\SystemStatusMessage;
use Database\Seeders\PlatformOperationsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['api.enabled' => false]);
    }

    public function test_bootstrap_fails_open_when_no_policy_is_configured(): void
    {
        $this->getJson('/api/v1/bootstrap?platform=android_mobile&build=16&version=1.4.13')
            ->assertOk()
            ->assertJsonPath('data.platform', 'android_mobile')
            ->assertJsonPath('data.system_status.severity', 'operational')
            ->assertJsonPath('data.maintenance.active', false)
            ->assertJsonPath('data.version_policy.status', 'supported');
    }

    public function test_advisories_are_platform_targeted_and_revisioned(): void
    {
        $message = SystemStatusMessage::query()->create([
            'title' => 'Playback migration',
            'message' => 'Some sources may start more slowly.',
            'severity' => 'degraded',
            'platforms' => ['android_mobile'],
            'affected_services' => ['playback'],
            'is_active' => true,
            'show_on_home' => true,
        ]);

        $this->getJson('/api/v1/bootstrap?platform=android_mobile&build=16')
            ->assertOk()
            ->assertJsonPath('data.messages.0.id', $message->id)
            ->assertJsonPath('data.messages.0.severity', 'degraded');

        $this->getJson('/api/v1/bootstrap?platform=ios&build=7')
            ->assertOk()
            ->assertJsonCount(0, 'data.messages');

        $message->update(['message' => 'Playback has stabilised.']);
        $this->assertSame(2, $message->fresh()->revision);
    }

    public function test_minimum_build_and_latest_build_are_compared_numerically(): void
    {
        PlatformVersionPolicy::query()->create([
            'platform' => 'android_mobile',
            'latest_version' => '1.10.0',
            'latest_build' => 110,
            'minimum_version' => '1.9.0',
            'minimum_build' => 109,
            'update_type' => 'optional',
            'update_url' => 'https://play.google.com/store/apps/details?id=com.naraboxtv.app',
            'prompt_enabled' => true,
            'is_active' => true,
        ]);

        $this->getJson('/api/v1/bootstrap?platform=android_mobile&build=108&version=1.8.9')
            ->assertOk()->assertJsonPath('data.version_policy.status', 'required');
        $this->getJson('/api/v1/bootstrap?platform=android_mobile&build=109&version=1.9.0')
            ->assertOk()->assertJsonPath('data.version_policy.status', 'optional');
        $this->getJson('/api/v1/bootstrap?platform=android_mobile&build=110&version=1.10.0')
            ->assertOk()->assertJsonPath('data.version_policy.status', 'supported');

        $this->withHeaders([
            'X-NaraBox-Platform' => 'android_mobile',
            'X-NaraBox-Build' => '108',
            'X-NaraBox-Version' => '1.8.9',
        ])->getJson('/api/v1/movies')
            ->assertStatus(426)
            ->assertJsonPath('code', 'APP_UPDATE_REQUIRED');
    }

    public function test_full_maintenance_blocks_only_targeted_platform_and_never_blocks_bootstrap(): void
    {
        SystemStatusMessage::query()->create([
            'title' => 'Routine status',
            'message' => 'Other services are healthy.',
            'severity' => 'operational',
            'platforms' => ['android_mobile'],
            'is_active' => true,
        ]);
        MaintenanceWindow::query()->create([
            'title' => 'Mobile maintenance',
            'message' => 'Mobile upgrades are in progress.',
            'mode' => 'full',
            'platforms' => ['android_mobile'],
            'is_active' => true,
        ]);

        $this->withHeader('X-NaraBox-Platform', 'android_mobile')->getJson('/api/v1/movies')
            ->assertStatus(503)
            ->assertJsonPath('code', 'PLATFORM_MAINTENANCE');

        $this->withHeader('X-NaraBox-Platform', 'nextjs_web')->getJson('/api/v1/movies')
            ->assertStatus(200);

        $this->withHeader('X-NaraBox-Platform', 'android_mobile')->getJson('/api/v1/bootstrap?build=16')
            ->assertOk()
            ->assertJsonPath('data.maintenance.blocking', true)
            ->assertJsonPath('data.system_status.severity', 'maintenance');
    }

    public function test_partial_maintenance_enforces_only_the_selected_feature(): void
    {
        MaintenanceWindow::query()->create([
            'title' => 'Payments paused',
            'message' => 'Browsing and playback still work.',
            'mode' => 'partial',
            'platforms' => ['all'],
            'affected_features' => ['payments'],
            'available_features' => ['catalogue', 'playback'],
            'is_active' => true,
        ]);

        $this->withHeader('X-NaraBox-Platform', 'ios')->getJson('/api/v1/payment-gateways')
            ->assertStatus(503)
            ->assertJsonPath('code', 'FEATURE_MAINTENANCE')
            ->assertJsonPath('feature', 'payments');

        $this->withHeader('X-NaraBox-Platform', 'ios')->postJson('/api/v1/iotec/webhook')
            ->assertStatus(503)
            ->assertJsonPath('feature', 'mobile_money');

        $this->withHeader('X-NaraBox-Platform', 'ios')->getJson('/api/v1/movies')->assertOk();
    }

    public function test_expired_windows_are_ignored_and_deactivated_by_the_scheduler_command(): void
    {
        $window = MaintenanceWindow::query()->create([
            'title' => 'Finished work',
            'message' => 'This should no longer block users.',
            'mode' => 'full',
            'platforms' => ['all'],
            'is_active' => true,
            'ends_at' => now()->subMinute(),
        ]);

        $this->withHeader('X-NaraBox-Platform', 'android_mobile')->getJson('/api/v1/movies')->assertOk();
        $this->artisan('operations:expire')->assertSuccessful();
        $this->assertFalse($window->fresh()->is_active);
    }

    public function test_common_operations_seeder_is_editable_and_idempotent(): void
    {
        $this->seed(PlatformOperationsSeeder::class);

        $this->assertDatabaseCount('platform_version_policies', 3);
        $this->assertDatabaseCount('maintenance_windows', 5);
        $this->assertDatabaseCount('system_status_messages', 7);
        $this->assertDatabaseHas('platform_version_policies', [
            'platform' => 'android_mobile',
            'latest_version' => '1.4.11',
            'latest_build' => 14,
            'minimum_build' => 14,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('platform_version_policies', [
            'platform' => 'ios',
            'latest_version' => '1.4.2',
            'latest_build' => 7,
            'is_active' => false,
            'update_url' => null,
        ]);
        $this->assertDatabaseHas('platform_version_policies', [
            'platform' => 'android_tv',
            'latest_version' => '1.4.13',
            'latest_build' => 16,
            'minimum_build' => 16,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('system_status_messages', [
            'preset_key' => 'all_systems_operational',
            'severity' => 'operational',
            'is_active' => true,
        ]);

        $preset = MaintenanceWindow::query()->where('preset_key', 'playback_recovery')->firstOrFail();
        $preset->update(['message' => 'Edited by operations.']);

        $this->seed(PlatformOperationsSeeder::class);

        $this->assertDatabaseCount('platform_version_policies', 3);
        $this->assertDatabaseCount('maintenance_windows', 5);
        $this->assertDatabaseCount('system_status_messages', 7);
        $this->assertSame('Edited by operations.', $preset->fresh()->message);
    }
}
