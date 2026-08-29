<?php

namespace App\Services;

use App\Models\SecurityIdentity;
use App\Models\SecurityRule;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\IpUtils;

class SecurityRuleService
{
    public function __construct(private readonly IdentityNormalizer $normalizer) {}

    public function findIdentity(array $identities, array $actions): ?SecurityIdentity
    {
        if (! Schema::hasTable('security_identities')) {
            return null;
        }

        $identities = $this->expandedIdentities($identities);
        foreach ($identities as $type => $value) {
            $normalized = $this->normalizer->forType((string) $type, is_scalar($value) ? (string) $value : null);
            if ($normalized === '') {
                continue;
            }
            $identity = SecurityIdentity::effective()
                ->where('identity_type', strtoupper((string) $type))
                ->where('value_hash', hash('sha256', $normalized))
                ->whereIn('action', $actions)
                ->orderByRaw("FIELD(risk_level, 'CRITICAL', 'HIGH', 'ELEVATED', 'WATCH', 'NORMAL')")
                ->first();
            if ($identity) {
                $identity->increment('hit_count');
                $identity->forceFill(['last_hit_at' => now()])->save();

                return $identity;
            }
        }

        if ($ip = ($identities['IP'] ?? null)) {
            $cidrIdentity = SecurityIdentity::effective()
                ->where('identity_type', 'CIDR')
                ->whereIn('action', $actions)
                ->get()
                ->first(fn (SecurityIdentity $identity) => IpUtils::checkIp((string) $ip, (string) $identity->normalized_value));
            if ($cidrIdentity) {
                $cidrIdentity->increment('hit_count');
                $cidrIdentity->forceFill(['last_hit_at' => now()])->save();

                return $cidrIdentity;
            }
        }

        return null;
    }

    public function findRule(array $identities, array $actions): ?SecurityRule
    {
        if (! Schema::hasTable('security_rules')) {
            return null;
        }

        $identities = $this->expandedIdentities($identities);
        $types = array_map('strtoupper', array_keys($identities));
        $rules = SecurityRule::effective()->whereIn('identity_type', $types)
            ->whereIn('action', $actions)->orderByDesc('priority')->get();

        foreach ($rules as $rule) {
            $raw = (string) ($identities[$rule->identity_type] ?? $identities[strtolower($rule->identity_type)] ?? '');
            if ($raw !== '' && $this->matches($rule, $raw)) {
                $rule->increment('hit_count');
                $rule->forceFill(['last_hit_at' => now()])->save();

                return $rule;
            }
        }

        return null;
    }

    public function matches(SecurityRule $rule, string $candidate): bool
    {
        if ($rule->identity_type === 'CIDR') {
            return filter_var($candidate, FILTER_VALIDATE_IP) !== false
                && IpUtils::checkIp($candidate, trim((string) $rule->pattern));
        }

        $normalized = $this->normalizer->forType($rule->identity_type, $candidate);
        $pattern = (string) ($rule->normalized_pattern ?: $this->normalizer->forType($rule->identity_type, $rule->pattern));

        return match ($rule->matching_mode) {
            'EXACT' => hash_equals((string) $rule->pattern, $candidate),
            'NORMALIZED_EXACT' => hash_equals($pattern, $normalized),
            'TOKEN_SET' => hash_equals($this->normalizer->tokenSet((string) $rule->pattern), $this->normalizer->tokenSet($candidate)),
            'CONTAINS' => $pattern !== '' && str_contains($normalized, $pattern),
            'PREFIX' => $pattern !== '' && str_starts_with($normalized, $pattern),
            'SUFFIX' => $pattern !== '' && str_ends_with($normalized, $pattern),
            'REGEX' => $this->safeRegexMatch((string) $rule->pattern, $candidate),
            default => false,
        };
    }

    public function storeIdentity(string $type, string $value, string $action, string $reason, array $attributes = []): SecurityIdentity
    {
        $normalized = $this->normalizer->forType($type, $value);

        return SecurityIdentity::updateOrCreate(
            ['identity_type' => strtoupper($type), 'value_hash' => hash('sha256', $normalized), 'action' => strtoupper($action)],
            array_merge([
                'normalized_value' => $normalized,
                'enabled' => true,
                'risk_level' => 'HIGH',
                'reason' => $reason,
                'source' => 'MANUAL',
            ], $attributes)
        );
    }

    private function safeRegexMatch(string $pattern, string $candidate): bool
    {
        if ($pattern === '' || strlen($pattern) > 500) {
            return false;
        }
        $expression = '~(*LIMIT_MATCH=100000)(*LIMIT_RECURSION=10000)'.str_replace('~', '\\~', $pattern).'~iu';

        return @preg_match($expression, $candidate) === 1;
    }

    private function expandedIdentities(array $identities): array
    {
        $expanded = [];
        foreach ($identities as $type => $value) {
            $expanded[strtoupper((string) $type)] = $value;
        }
        if (! isset($expanded['EMAIL_DOMAIN']) && is_string($expanded['EMAIL'] ?? null) && str_contains($expanded['EMAIL'], '@')) {
            $expanded['EMAIL_DOMAIN'] = str($expanded['EMAIL'])->afterLast('@')->toString();
        }
        if (! isset($expanded['SOCIAL_PROVIDER_ID']) && (isset($expanded['GOOGLE_SUB']) || isset($expanded['APPLE_SUB']))) {
            $expanded['SOCIAL_PROVIDER_ID'] = $expanded['GOOGLE_SUB'] ?? $expanded['APPLE_SUB'];
        }
        if (! isset($expanded['CIDR']) && isset($expanded['IP'])) {
            $expanded['CIDR'] = $expanded['IP'];
        }

        return $expanded;
    }
}
