<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminAlertSetting extends Model
{
    protected $fillable = [
        'alert_email',
        'alert_on_registration',
        'alert_on_payment_success',
        'alert_on_payment_failure',
        'alert_on_content_request',
        'alert_on_comment',
        'alert_on_comment_reply',
        'alert_on_playback_issue',
        'alert_on_campaign_summary',
        'playback_failure_threshold',
        'slow_start_threshold_ms',
        'high_failure_rate_threshold',
    ];

    protected function casts(): array
    {
        return [
            'alert_on_registration' => 'boolean',
            'alert_on_payment_success' => 'boolean',
            'alert_on_payment_failure' => 'boolean',
            'alert_on_content_request' => 'boolean',
            'alert_on_comment' => 'boolean',
            'alert_on_comment_reply' => 'boolean',
            'alert_on_playback_issue' => 'boolean',
            'alert_on_campaign_summary' => 'boolean',
            'playback_failure_threshold' => 'integer',
            'slow_start_threshold_ms' => 'integer',
            'high_failure_rate_threshold' => 'integer',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate(
            ['id' => 1],
            ['alert_email' => 'smoggrafton@gmail.com']
        );
    }
}
