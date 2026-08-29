<?php

namespace App\Services;

use Normalizer;

class IdentityNormalizer
{
    public function forType(string $type, ?string $value): string
    {
        $type = strtoupper(trim($type));

        return match ($type) {
            'EMAIL', 'EMAIL_DOMAIN' => mb_strtolower(trim((string) $value)),
            'PHONE', 'PAYER_PHONE' => $this->phone((string) $value),
            'DISPLAY_NAME', 'USERNAME' => $this->name((string) $value),
            'IP', 'CIDR' => trim((string) $value),
            default => mb_strtolower(trim((string) $value)),
        };
    }

    public function name(string $value): string
    {
        if (class_exists(Normalizer::class)) {
            $value = Normalizer::normalize($value, Normalizer::FORM_KC) ?: $value;
        }

        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    public function tokenSet(string $value): string
    {
        $tokens = array_values(array_unique(array_filter(explode(' ', $this->name($value)))));
        sort($tokens, SORT_STRING);

        return implode(' ', $tokens);
    }

    public function phone(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';
        if (strlen($digits) === 10 && str_starts_with($digits, '0')) {
            return '256'.substr($digits, 1);
        }
        if (strlen($digits) === 9 && str_starts_with($digits, '7')) {
            return '256'.$digits;
        }

        return $digits;
    }
}
