<?php

namespace App\Services;

use App\Models\CreatorAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class CreatorAuditService
{
    public function record(
        string $event,
        ?User $actor = null,
        ?User $subject = null,
        Model|string|null $auditable = null,
        array $oldValues = [],
        array $newValues = [],
        array $metadata = []
    ): CreatorAuditLog {
        $request = request();

        return CreatorAuditLog::create([
            'actor_id' => $actor?->id,
            'subject_user_id' => $subject?->id,
            'event' => $event,
            'auditable_type' => $auditable instanceof Model ? $auditable::class : (is_string($auditable) ? $auditable : null),
            'auditable_id' => $auditable instanceof Model ? (string) $auditable->getKey() : null,
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'ip_address' => app()->runningInConsole() ? null : $request->ip(),
            'user_agent' => app()->runningInConsole() ? null : mb_substr((string) $request->userAgent(), 0, 1000),
            'metadata' => $metadata ?: null,
        ]);
    }
}
