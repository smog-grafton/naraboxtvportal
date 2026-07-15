<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class PhoneVerificationCode extends Model
{
    protected $fillable = [
        'phone',
        'code',
        'expires_at',
        'used',
        'attempts',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used' => 'boolean',
    ];

    public function isValid(): bool
    {
        return !$this->used && $this->expires_at->isFuture();
    }

    public static function generateCode(): string
    {
        return str_pad((string) rand(100000, 999999), 6, '0', STR_PAD_LEFT);
    }

    public static function createForPhone(string $phone): self
    {
        // Invalidate any existing unused codes for this phone
        self::where('phone', $phone)
            ->where('used', false)
            ->update(['used' => true]);

        return self::create([
            'phone' => $phone,
            'code' => self::generateCode(),
            'expires_at' => Carbon::now()->addMinutes(10),
            'used' => false,
            'attempts' => 0,
        ]);
    }
}

