<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Carbon\Carbon;

class PasswordReset extends Model
{
    protected $fillable = [
        'email',
        'token',
        'expires_at',
        'used',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used' => 'boolean',
    ];

    public function isValid(): bool
    {
        return !$this->used && $this->expires_at->isFuture();
    }

    public static function createForEmail(string $email): self
    {
        // Invalidate any existing tokens for this email
        self::where('email', $email)
            ->where('used', false)
            ->update(['used' => true]);

        return self::create([
            'email' => $email,
            'token' => Str::random(64),
            'expires_at' => Carbon::now()->addHours(1),
            'used' => false,
        ]);
    }

    public static function findByToken(string $token): ?self
    {
        return self::where('token', $token)
            ->where('used', false)
            ->first();
    }
}
