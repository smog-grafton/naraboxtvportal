<?php

namespace App\Models;

use App\Models\Concerns\AuditsOperationalChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class PlatformVersionPolicy extends Model
{
    use AuditsOperationalChanges;

    protected $fillable = [
        'platform', 'latest_version', 'latest_build', 'minimum_version', 'minimum_build',
        'update_type', 'update_url', 'title', 'message', 'release_notes', 'effective_at',
        'grace_period_ends_at', 'prompt_enabled', 'is_active', 'revision',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'latest_build' => 'integer', 'minimum_build' => 'integer',
            'effective_at' => 'datetime', 'grace_period_ends_at' => 'datetime',
            'prompt_enabled' => 'boolean', 'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (PlatformVersionPolicy $policy): void {
            if (! in_array($policy->platform, ['android_mobile', 'ios', 'android_tv'], true)) {
                throw ValidationException::withMessages([
                    'platform' => 'Select Android mobile, iOS, or Android TV.',
                ]);
            }
            if (! in_array($policy->update_type, ['optional', 'required'], true)) {
                throw ValidationException::withMessages([
                    'update_type' => 'The update type must be optional or required.',
                ]);
            }
            if ((int) $policy->minimum_build > (int) $policy->latest_build) {
                throw ValidationException::withMessages([
                    'minimum_build' => 'The minimum supported build cannot be greater than the latest public build.',
                ]);
            }
            if ($policy->is_active && blank($policy->update_url)) {
                throw ValidationException::withMessages([
                    'update_url' => 'An active policy needs a store URL so update screens always have a valid destination.',
                ]);
            }
            if ($policy->effective_at && $policy->grace_period_ends_at && $policy->grace_period_ends_at->lessThan($policy->effective_at)) {
                throw ValidationException::withMessages([
                    'grace_period_ends_at' => 'The grace period cannot end before the policy becomes effective.',
                ]);
            }
        });
    }
}
