<?php

namespace App\Models;

use App\Models\Concerns\AuditsOperationalChanges;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class MaintenanceWindow extends Model
{
    use AuditsOperationalChanges;

    protected $fillable = [
        'preset_key', 'title', 'message', 'details', 'mode', 'platforms', 'affected_features',
        'available_features', 'starts_at', 'ends_at', 'is_active', 'allow_read_only',
        'priority', 'revision', 'reason', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'platforms' => 'array', 'affected_features' => 'array', 'available_features' => 'array',
            'starts_at' => 'datetime', 'ends_at' => 'datetime',
            'is_active' => 'boolean', 'allow_read_only' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (MaintenanceWindow $window): void {
            if (in_array('all', $window->platforms ?? [], true) && count($window->platforms ?? []) > 1) {
                throw ValidationException::withMessages([
                    'platforms' => 'Choose All platforms by itself, or choose individual platforms.',
                ]);
            }
            if ($window->mode === 'partial' && empty($window->affected_features)) {
                throw ValidationException::withMessages([
                    'affected_features' => 'Partial maintenance must select at least one unavailable feature.',
                ]);
            }
            if (array_intersect($window->affected_features ?? [], $window->available_features ?? [])) {
                throw ValidationException::withMessages([
                    'available_features' => 'A feature cannot be both unavailable and still available.',
                ]);
            }
            if ($window->starts_at && $window->ends_at && $window->ends_at->lessThanOrEqualTo($window->starts_at)) {
                throw ValidationException::withMessages([
                    'ends_at' => 'The end time must be after the start time.',
                ]);
            }
        });
    }

    public function scopeCurrentlyActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }
}
