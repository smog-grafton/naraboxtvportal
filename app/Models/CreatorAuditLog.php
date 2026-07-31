<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreatorAuditLog extends Model
{
    protected $fillable = [
        'actor_id', 'subject_user_id', 'event', 'auditable_type',
        'auditable_id', 'old_values', 'new_values', 'ip_address',
        'user_agent', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'metadata' => 'array',
        ];
    }
}
