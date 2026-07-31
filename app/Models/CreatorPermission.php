<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreatorPermission extends Model
{
    protected $fillable = [
        'user_id',
        'creator_application_id',
        'permission_preset',
        'capabilities',
        'identity_verified',
        'draft_submission_enabled',
        'publishing_enabled',
        'monetization_enabled',
        'withdrawals_enabled',
        'profile_editing_enabled',
        'is_suspended',
        'is_revoked',
        'restriction_reason',
        'creator_share_bps_override',
        'withdrawal_hold_until',
        'approved_by',
        'identity_verified_at',
        'publishing_enabled_at',
        'monetization_enabled_at',
        'withdrawals_enabled_at',
        'suspended_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'identity_verified' => 'boolean',
            'capabilities' => 'array',
            'draft_submission_enabled' => 'boolean',
            'publishing_enabled' => 'boolean',
            'monetization_enabled' => 'boolean',
            'withdrawals_enabled' => 'boolean',
            'profile_editing_enabled' => 'boolean',
            'is_suspended' => 'boolean',
            'is_revoked' => 'boolean',
            'withdrawal_hold_until' => 'datetime',
            'identity_verified_at' => 'datetime',
            'publishing_enabled_at' => 'datetime',
            'monetization_enabled_at' => 'datetime',
            'withdrawals_enabled_at' => 'datetime',
            'suspended_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(CreatorApplication::class, 'creator_application_id');
    }

    public function allows(string $capability): bool
    {
        if ($this->is_suspended || $this->is_revoked) {
            return false;
        }

        if (array_key_exists($capability, $this->getAttributes())) {
            return (bool) $this->getAttribute($capability);
        }

        $capabilities = $this->capabilities;
        if (! is_array($capabilities)) {
            $capabilities = config('creator.permission_presets.'.$this->permission_preset, []);
        }

        if (! in_array($capability, $capabilities, true)) {
            return false;
        }

        return match ($capability) {
            'publish_without_review' => (bool) $this->publishing_enabled,
            'set_rental_price', 'set_purchase_price', 'select_subscription_plans' => (bool) $this->monetization_enabled,
            'request_withdrawal' => (bool) $this->withdrawals_enabled
                && (! $this->withdrawal_hold_until || $this->withdrawal_hold_until->isPast()),
            'manage_public_profile' => (bool) $this->profile_editing_enabled,
            default => (bool) $this->draft_submission_enabled,
        };
    }

    public function normalizedCapabilities(): array
    {
        $known = collect(config('creator.permission_catalog', []))
            ->flatMap(fn (array $group) => array_keys($group))
            ->values()
            ->all();
        $selected = is_array($this->capabilities)
            ? $this->capabilities
            : config('creator.permission_presets.'.$this->permission_preset, []);

        return array_values(array_intersect($known, $selected));
    }

    public static function capabilityOptions(): array
    {
        return collect(config('creator.permission_catalog', []))
            ->flatMap(fn (array $group, string $label) => collect($group)
                ->mapWithKeys(fn (string $name, string $key) => [$key => "{$label} — {$name}"]))
            ->all();
    }

    public function syncBroadGatesFromCapabilities(): void
    {
        $capabilities = $this->normalizedCapabilities();
        $this->forceFill([
            'capabilities' => $capabilities,
            'draft_submission_enabled' => count(array_intersect($capabilities, [
                'create_movies', 'edit_own_movies', 'submit_movies', 'create_tv_shows',
                'manage_seasons', 'manage_episodes', 'upload_media',
                'submit_telegram_links', 'submit_remote_urls',
            ])) > 0,
            'publishing_enabled' => in_array('publish_without_review', $capabilities, true),
            'monetization_enabled' => count(array_intersect($capabilities, [
                'set_rental_price', 'set_purchase_price', 'select_subscription_plans',
            ])) > 0,
            'withdrawals_enabled' => in_array('request_withdrawal', $capabilities, true),
            'profile_editing_enabled' => in_array('manage_public_profile', $capabilities, true),
        ]);
    }

    protected static function booted(): void
    {
        static::saving(function (CreatorPermission $permission): void {
            if (is_array($permission->capabilities)) {
                $permission->syncBroadGatesFromCapabilities();
            }
        });
    }
}
