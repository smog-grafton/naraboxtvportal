<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreatorNotificationDelivery extends Model
{
    protected $fillable = [
        'user_id',
        'user_notification_id',
        'template_name',
        'event_key',
        'email_status',
        'email_sent_at',
        'email_error',
        'safe_metadata',
    ];

    protected function casts(): array
    {
        return [
            'email_sent_at' => 'datetime',
            'safe_metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function notification(): BelongsTo
    {
        return $this->belongsTo(UserNotification::class, 'user_notification_id');
    }
}
