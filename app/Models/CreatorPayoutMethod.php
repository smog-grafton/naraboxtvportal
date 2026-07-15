<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class CreatorPayoutMethod extends Model
{
    protected $fillable = [
        'user_id',
        'method_type',
        'provider',
        'phone_number',
        'account_name',
        'account_number',
        'bank_name',
        'bank_code',
        'is_default',
        'is_verified',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_verified' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function getMaskedPhoneAttribute(): ?string
    {
        if (!$this->phone_number || strlen($this->phone_number) < 4) {
            return null;
        }
        return '****' . substr($this->phone_number, -4);
    }

    public function getMaskedAccountAttribute(): ?string
    {
        if (!$this->account_number || strlen($this->account_number) < 4) {
            return null;
        }
        return '****' . substr($this->account_number, -4);
    }
}
