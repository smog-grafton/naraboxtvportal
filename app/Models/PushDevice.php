<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushDevice extends Model
{
    protected $fillable = [
        'user_id',
        'platform',
        'provider',
        'token',
        'device_id',
        'device_name',
        'app_version',
        'is_active',
        'notifications_enabled',
        'marketing_opt_in',
        'tags',
        'last_seen_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'notifications_enabled' => 'boolean',
        'marketing_opt_in' => 'boolean',
        'tags' => 'array',
        'last_seen_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
