<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TvDevice extends Model
{
    protected $fillable = [
        'user_id',
        'device_identifier',
        'name',
        'platform',
        'app_version',
        'activated_at',
        'last_seen_at',
        'last_ip',
        'last_user_agent',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'activated_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deviceCodes(): HasMany
    {
        return $this->hasMany(TvDeviceCode::class);
    }

    public function checkoutSessions(): HasMany
    {
        return $this->hasMany(TvCheckoutSession::class);
    }
}
