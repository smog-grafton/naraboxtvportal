<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

final class MoneyService
{
    public static function toMinor(int|string|float $amount, string $currency = 'UGX'): int
    {
        $normalized = trim((string) $amount);
        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $normalized)) {
            throw ValidationException::withMessages(['amount' => ['Use a valid monetary amount with at most two decimal places.']]);
        }

        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $exponent = strtoupper($currency) === 'UGX' ? 0 : 2;
        if ($exponent === 0) {
            if (trim($fraction, '0') !== '') {
                throw ValidationException::withMessages(['amount' => ['UGX amounts must use whole shillings.']]);
            }

            return (int) $whole;
        }

        $fraction = str_pad($fraction, $exponent, '0');

        return ((int) $whole * (10 ** $exponent)) + (int) substr($fraction, 0, $exponent);
    }

    public static function fromMinor(int $amountMinor, string $currency = 'UGX'): string
    {
        if (strtoupper($currency) === 'UGX') {
            return (string) $amountMinor;
        }

        $sign = $amountMinor < 0 ? '-' : '';
        $absolute = abs($amountMinor);

        return $sign.intdiv($absolute, 100).'.'.str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function share(int $amountMinor, int $basisPoints): int
    {
        return intdiv(($amountMinor * $basisPoints) + 5000, 10000);
    }
}
