<?php

namespace App\Models;

use App\Services\IdentityNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class SecurityIdentity extends Model
{
    protected $fillable = [
        'identity_type', 'normalized_value', 'value_hash', 'action', 'enabled',
        'risk_level', 'reason', 'notes', 'source', 'created_by', 'expires_at',
        'hit_count', 'last_hit_at',
    ];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'expires_at' => 'datetime', 'last_hit_at' => 'datetime'];
    }

    public function scopeEffective(Builder $query): Builder
    {
        return $query->where('enabled', true)
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    protected static function booted(): void
    {
        static::saving(function (SecurityIdentity $identity): void {
            $identity->identity_type = strtoupper(trim((string) $identity->identity_type));
            $identity->action = strtoupper(trim((string) $identity->action));
            $identity->risk_level = strtoupper(trim((string) $identity->risk_level));
            $normalizer = app(IdentityNormalizer::class);
            $identity->normalized_value = $normalizer->forType($identity->identity_type, (string) $identity->normalized_value);
            if ($identity->normalized_value === '' || ($identity->identity_type === 'CIDR' && ! static::validCidr($identity->normalized_value))) {
                throw ValidationException::withMessages(['normalized_value' => 'Enter a valid identity value.']);
            }
            $identity->value_hash = hash('sha256', $identity->normalized_value);
            $identity->created_by ??= auth()->id();
        });
    }

    private static function validCidr(string $value): bool
    {
        if (! str_contains($value, '/')) {
            return false;
        }
        [$address, $prefix] = explode('/', $value, 2);
        if (filter_var($address, FILTER_VALIDATE_IP) === false || ! ctype_digit($prefix)) {
            return false;
        }

        return (int) $prefix >= 0 && (int) $prefix <= (str_contains($address, ':') ? 128 : 32);
    }
}
