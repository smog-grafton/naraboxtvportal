<?php

namespace Database\Seeders;

use App\Models\MaintenanceWindow;
use App\Models\PlatformVersionPolicy;
use App\Models\SystemStatusMessage;
use Illuminate\Database\Seeder;

class PlatformOperationsSeeder extends Seeder
{
    private const PLAY_STORE_URL = 'https://play.google.com/store/apps/details?id=com.naraboxtv.app';

    public function run(): void
    {
        $this->seedVersionPolicies();
        $this->seedMaintenancePresets();
        $this->seedStatusPresets();
    }

    private function seedVersionPolicies(): void
    {
        $policies = [
            [
                'platform' => 'android_mobile',
                'latest_version' => '1.4.11',
                'latest_build' => 14,
                'minimum_version' => '1.4.11',
                'minimum_build' => 14,
                'update_type' => 'optional',
                'update_url' => self::PLAY_STORE_URL,
                'title' => 'Update NaraBox',
                'message' => 'Update NaraBox to keep streaming.',
                'release_notes' => 'Current public Android mobile release.',
                'prompt_enabled' => true,
                'is_active' => true,
            ],
            [
                'platform' => 'ios',
                'latest_version' => '1.4.2',
                'latest_build' => 7,
                'minimum_version' => '1.4.2',
                'minimum_build' => 7,
                'update_type' => 'optional',
                'update_url' => null,
                'title' => 'Update NaraBox',
                'message' => 'Update NaraBox to keep streaming.',
                'release_notes' => 'Add the exact App Store URL, then activate this policy.',
                'prompt_enabled' => true,
                'is_active' => false,
            ],
            [
                'platform' => 'android_tv',
                'latest_version' => '1.4.13',
                'latest_build' => 16,
                'minimum_version' => '1.4.13',
                'minimum_build' => 16,
                'update_type' => 'optional',
                'update_url' => self::PLAY_STORE_URL,
                'title' => 'Update NaraBox TV',
                'message' => 'Update NaraBox TV to continue watching.',
                'release_notes' => 'Current Android TV release build.',
                'prompt_enabled' => true,
                'is_active' => true,
            ],
        ];

        foreach ($policies as $policy) {
            // Platform is unique. firstOrCreate makes this safe to rerun without
            // replacing an administrator's later release or support decision.
            PlatformVersionPolicy::query()->firstOrCreate(
                ['platform' => $policy['platform']],
                $policy,
            );
        }
    }

    private function seedMaintenancePresets(): void
    {
        $presets = [
            [
                'preset_key' => 'apps_tv_full_maintenance',
                'title' => 'Apps & TV maintenance',
                'message' => 'NaraBox is being updated. The website is still available.',
                'details' => 'Use this when Android, iPhone, and TV must pause while the website remains online.',
                'mode' => 'full',
                'platforms' => ['android_mobile', 'ios', 'android_tv'],
                'affected_features' => [],
                'available_features' => [],
                'is_active' => false,
                'allow_read_only' => false,
                'priority' => 100,
                'reason' => 'Reusable full-maintenance preset for native apps and TV only.',
            ],
            [
                'preset_key' => 'playback_recovery',
                'title' => 'Playback maintenance',
                'message' => 'Watching and downloads are briefly unavailable. Browsing still works.',
                'details' => 'Use while video sources, streaming servers, or download delivery are being restored.',
                'mode' => 'partial',
                'platforms' => ['all'],
                'affected_features' => ['playback', 'downloads'],
                'available_features' => ['catalogue', 'search', 'news', 'vj_pages', 'authentication'],
                'is_active' => false,
                'allow_read_only' => false,
                'priority' => 80,
                'reason' => 'Reusable playback and download recovery preset.',
            ],
            [
                'preset_key' => 'payment_services_maintenance',
                'title' => 'Payments temporarily paused',
                'message' => 'Watching still works, but payments are briefly unavailable.',
                'details' => 'Use for Mobile Money, card, subscription, rental, or purchase interruptions.',
                'mode' => 'partial',
                'platforms' => ['all'],
                'affected_features' => ['subscriptions', 'payments', 'mobile_money', 'card_payments'],
                'available_features' => ['catalogue', 'playback', 'search', 'news', 'vj_pages'],
                'is_active' => false,
                'allow_read_only' => false,
                'priority' => 70,
                'reason' => 'Reusable payments maintenance preset.',
            ],
            [
                'preset_key' => 'sign_in_pairing_maintenance',
                'title' => 'Sign-in maintenance',
                'message' => 'Sign-in and TV pairing are briefly unavailable. Public browsing still works.',
                'details' => 'Use while login, account sessions, or TV device approval is being repaired.',
                'mode' => 'partial',
                'platforms' => ['all'],
                'affected_features' => ['authentication', 'pairing'],
                'available_features' => ['catalogue', 'search', 'news', 'vj_pages'],
                'is_active' => false,
                'allow_read_only' => false,
                'priority' => 75,
                'reason' => 'Reusable authentication and pairing maintenance preset.',
            ],
            [
                'preset_key' => 'read_only_safety_mode',
                'title' => 'NaraBox is in read-only mode',
                'message' => 'You can browse and watch. Account and payment changes are paused.',
                'details' => 'Use when reads are safe but writes must stop during database or infrastructure work.',
                'mode' => 'read_only',
                'platforms' => ['all'],
                'affected_features' => [],
                'available_features' => ['catalogue', 'playback', 'search', 'news', 'vj_pages'],
                'is_active' => false,
                'allow_read_only' => true,
                'priority' => 90,
                'reason' => 'Reusable write-protection preset.',
            ],
        ];

        foreach ($presets as $preset) {
            MaintenanceWindow::query()->firstOrCreate(
                ['preset_key' => $preset['preset_key']],
                $preset,
            );
        }
    }

    private function seedStatusPresets(): void
    {
        $presets = [
            [
                'preset_key' => 'all_systems_operational',
                'title' => 'All systems operational',
                'message' => 'Streaming and account services are working normally.',
                'severity' => 'operational',
                'platforms' => ['all'],
                'affected_services' => [],
                'is_active' => true,
                'is_dismissible' => false,
                'show_on_home' => true,
                'show_globally' => false,
                'priority' => 0,
            ],
            [
                'preset_key' => 'video_sources_unavailable',
                'title' => 'Some videos are unavailable',
                'message' => 'A few titles may not play while we restore their video sources.',
                'severity' => 'degraded',
                'platforms' => ['all'],
                'affected_services' => ['playback'],
                'is_active' => false,
                'is_dismissible' => true,
                'show_on_home' => true,
                'show_globally' => false,
                'priority' => 60,
            ],
            [
                'preset_key' => 'network_connectivity',
                'title' => 'Connectivity issues',
                'message' => 'Streaming may start slowly or reconnect while network service stabilises.',
                'severity' => 'degraded',
                'platforms' => ['all'],
                'affected_services' => ['catalogue', 'playback'],
                'is_active' => false,
                'is_dismissible' => true,
                'show_on_home' => true,
                'show_globally' => false,
                'priority' => 65,
            ],
            [
                'preset_key' => 'stronger_servers',
                'title' => 'Moving to stronger servers',
                'message' => 'Some features may be briefly limited while NaraBox capacity is upgraded.',
                'severity' => 'maintenance',
                'platforms' => ['all'],
                'affected_services' => ['catalogue', 'playback'],
                'is_active' => false,
                'is_dismissible' => false,
                'show_on_home' => true,
                'show_globally' => true,
                'priority' => 80,
            ],
            [
                'preset_key' => 'heavy_user_traffic',
                'title' => 'Heavy traffic',
                'message' => 'Loading may be slower than usual while more capacity comes online.',
                'severity' => 'degraded',
                'platforms' => ['all'],
                'affected_services' => ['catalogue', 'playback'],
                'is_active' => false,
                'is_dismissible' => true,
                'show_on_home' => true,
                'show_globally' => false,
                'priority' => 70,
            ],
            [
                'preset_key' => 'security_response',
                'title' => 'Security response in progress',
                'message' => 'Some services are restricted while we protect NaraBox.',
                'severity' => 'critical',
                'platforms' => ['all'],
                'affected_services' => ['authentication', 'payments'],
                'is_active' => false,
                'is_dismissible' => false,
                'show_on_home' => true,
                'show_globally' => true,
                'priority' => 100,
            ],
            [
                'preset_key' => 'development_stage_notice',
                'title' => 'NaraBox TV is still growing',
                'message' => 'You may notice brief changes while we improve the TV experience.',
                'severity' => 'advisory',
                'platforms' => ['android_tv'],
                'affected_services' => ['catalogue', 'playback'],
                'is_active' => false,
                'is_dismissible' => true,
                'show_on_home' => true,
                'show_globally' => false,
                'priority' => 20,
            ],
        ];

        foreach ($presets as $preset) {
            SystemStatusMessage::query()->firstOrCreate(
                ['preset_key' => $preset['preset_key']],
                $preset,
            );
        }
    }
}
