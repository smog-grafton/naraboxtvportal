<?php

namespace App\Models\Concerns;

use App\Models\OperationalAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

trait AuditsOperationalChanges
{
    protected static function bootAuditsOperationalChanges(): void
    {
        static::creating(function (Model $model): void {
            if ($model->isFillable('revision') && ! $model->getAttribute('revision')) {
                $model->setAttribute('revision', 1);
            }
            if ($model->isFillable('created_by') && ! $model->getAttribute('created_by')) {
                $model->setAttribute('created_by', Auth::id());
            }
            if ($model->isFillable('updated_by') && ! $model->getAttribute('updated_by')) {
                $model->setAttribute('updated_by', Auth::id());
            }
        });

        static::updating(function (Model $model): void {
            if ($model->isFillable('updated_by')) {
                $model->setAttribute('updated_by', Auth::id());
            }
            if ($model->isFillable('revision')) {
                $model->setAttribute('revision', max(1, (int) $model->getAttribute('revision') + 1));
            }
        });

        static::created(fn (Model $model) => static::recordOperationalAudit($model, 'created', [], $model->getAttributes()));
        static::updated(fn (Model $model) => static::recordOperationalAudit($model, 'updated', $model->getOriginal(), $model->getChanges()));
        static::deleted(fn (Model $model) => static::recordOperationalAudit($model, 'deleted', $model->getOriginal(), []));
    }

    private static function recordOperationalAudit(Model $model, string $event, array $old, array $new): void
    {
        Cache::forever('operations:revision', ((int) Cache::get('operations:revision', 1)) + 1);

        OperationalAuditLog::query()->create([
            'actor_id' => Auth::id(),
            'event' => class_basename($model).'.'.$event,
            'auditable_type' => $model::class,
            'auditable_id' => (string) $model->getKey(),
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }
}
