<?php

namespace App\Models;

use App\Services\IdentityNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class SecurityRule extends Model
{
    protected $fillable = [
        'name', 'identity_type', 'matching_mode', 'pattern', 'normalized_pattern',
        'action', 'enabled', 'risk_level', 'reason', 'notes', 'priority',
        'created_by', 'expires_at', 'hit_count', 'last_hit_at',
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
        static::saving(function (SecurityRule $rule): void {
            $normalizer = app(IdentityNormalizer::class);
            $rule->identity_type = strtoupper(trim((string) $rule->identity_type));
            $rule->matching_mode = strtoupper(trim((string) $rule->matching_mode));
            $rule->action = strtoupper(trim((string) $rule->action));
            $rule->risk_level = strtoupper(trim((string) $rule->risk_level));
            if ($rule->matching_mode === 'REGEX') {
                $pattern = (string) $rule->pattern;
                $expression = '~(*LIMIT_MATCH=100000)(*LIMIT_RECURSION=10000)'.str_replace('~', '\\~', $pattern).'~iu';
                if ($pattern === '' || strlen($pattern) > 500 || @preg_match($expression, '') === false) {
                    throw ValidationException::withMessages(['pattern' => 'Enter a valid regular expression no longer than 500 characters.']);
                }
            }
            $rule->normalized_pattern = $rule->matching_mode === 'TOKEN_SET'
                ? $normalizer->tokenSet((string) $rule->pattern)
                : $normalizer->forType($rule->identity_type, (string) $rule->pattern);
        });
    }
}
