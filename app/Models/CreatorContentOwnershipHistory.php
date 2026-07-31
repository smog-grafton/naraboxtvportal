<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreatorContentOwnershipHistory extends Model
{
    protected $table = 'creator_content_ownership_history';

    protected $fillable = [
        'content_type', 'content_id', 'submitting_user_id', 'owner_type',
        'owner_id', 'previous_owner_type', 'previous_owner_id', 'action',
        'reason', 'actor_id', 'metadata', 'occurred_at',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'occurred_at' => 'datetime'];
    }
}
