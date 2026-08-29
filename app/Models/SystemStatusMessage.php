<?php

namespace App\Models;

use App\Models\Concerns\AuditsOperationalChanges;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class SystemStatusMessage extends Model
{
    use AuditsOperationalChanges;

    protected $fillable = [
        'preset_key', 'title', 'message', 'details', 'severity', 'platforms', 'affected_services',
        'starts_at', 'ends_at', 'is_active', 'is_dismissible', 'show_on_home',
        'show_globally', 'cta_label', 'cta_url', 'priority', 'revision',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'platforms' => 'array', 'affected_services' => 'array',
            'starts_at' => 'datetime', 'ends_at' => 'datetime',
            'is_active' => 'boolean', 'is_dismissible' => 'boolean',
            'show_on_home' => 'boolean', 'show_globally' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (SystemStatusMessage $message): void {
            if (in_array('all', $message->platforms ?? [], true) && count($message->platforms ?? []) > 1) {
                throw ValidationException::withMessages([
                    'platforms' => 'Choose All platforms by itself, or choose individual platforms.',
                ]);
            }
            if ($message->starts_at && $message->ends_at && $message->ends_at->lessThanOrEqualTo($message->starts_at)) {
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
