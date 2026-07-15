<?php

namespace Database\Seeders;

use App\Models\AdminAlertSetting;
use Illuminate\Database\Seeder;

class AdminAlertSettingSeeder extends Seeder
{
    public function run(): void
    {
        AdminAlertSetting::updateOrCreate(
            ['id' => 1],
            [
                'alert_email' => 'smoggrafton@gmail.com',
                'alert_on_registration' => true,
                'alert_on_payment_success' => true,
                'alert_on_payment_failure' => true,
                'alert_on_content_request' => true,
                'alert_on_comment' => true,
                'alert_on_comment_reply' => true,
                'alert_on_playback_issue' => true,
                'alert_on_campaign_summary' => true,
                'playback_failure_threshold' => 3,
                'slow_start_threshold_ms' => 8000,
                'high_failure_rate_threshold' => 25,
            ]
        );
    }
}
