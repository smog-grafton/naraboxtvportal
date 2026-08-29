<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountEnforcement extends Model
{
    protected $fillable = [
        'user_id', 'action', 'previous_status', 'new_status', 'reason', 'notes',
        'source', 'actor_user_id', 'security_event_id', 'started_at', 'expires_at', 'ended_at',
    ];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'expires_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
