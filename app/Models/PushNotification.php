<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PushNotification extends Model
{
    protected $fillable = [
        'title',
        'body',
        'image_url',
        'deep_link',
        'target_platform',
        'target_audience',
        'filters',
        'provider',
        'notification_type',
        'status',
        'sent_at',
        'success_count',
        'failure_count',
        'last_error',
    ];

    protected $casts = [
        'filters' => 'array',
        'sent_at' => 'datetime',
        'last_error' => 'array',
    ];
}
