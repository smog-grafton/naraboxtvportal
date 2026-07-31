<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class CreatorPayoutMethod extends Model
{
    protected $hidden = [
        'phone_number',
        'account_number',
        'protected_details',
    ];

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
        'protected_details',
        'details_fingerprint',
        'verification_status',
        'verified_by',
        'verified_at',
        'changed_at',
        'withdrawal_hold_until',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_verified' => 'boolean',
            'metadata' => 'array',
            'protected_details' => 'encrypted:array',
            'verified_at' => 'datetime',
            'changed_at' => 'datetime',
            'withdrawal_hold_until' => 'datetime',
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
        $phone = $this->protected_details['phone_number'] ?? $this->phone_number;
        if (!$phone || strlen($phone) < 4) {
            return null;
        }
        return '•••• ' . substr($phone, -4);
    }

    public function getMaskedAccountAttribute(): ?string
    {
        $account = $this->protected_details['account_number'] ?? $this->account_number;
        if (!$account || strlen($account) < 4) {
            return null;
        }
        return '•••• ' . substr($account, -4);
    }

    public function getMaskedAccountNameAttribute(): ?string
    {
        $name = trim((string) ($this->protected_details['account_name'] ?? $this->account_name));
        if ($name === '') {
            return null;
        }
        $parts = preg_split('/\s+/', $name) ?: [];

        return collect($parts)->map(
            fn (string $part) => mb_substr($part, 0, 1).str_repeat('•', max(2, mb_strlen($part) - 1))
        )->implode(' ');
    }

    public function destination(string $key): ?string
    {
        $protected = $this->protected_details ?? [];

        return $protected[$key] ?? $this->getAttribute($key);
    }
}
