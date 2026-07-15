<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TvDeviceCode extends Model
{
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_CONSUMED = 'CONSUMED';
    public const STATUS_EXPIRED = 'EXPIRED';

    protected $fillable = [
        'tv_device_id',
        'user_id',
        'user_code',
        'device_code_hash',
        'status',
        'expires_at',
        'approved_at',
        'consumed_at',
        'last_polled_at',
        'issued_ip',
        'issued_user_agent',
        'approved_ip',
        'approved_user_agent',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'approved_at' => 'datetime',
            'consumed_at' => 'datetime',
            'last_polled_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(TvDevice::class, 'tv_device_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
