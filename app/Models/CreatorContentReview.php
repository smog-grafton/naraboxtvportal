<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreatorContentReview extends Model
{
    protected $fillable = [
        'content_type', 'content_id', 'status', 'creator_message',
        'internal_notes', 'requested_changes', 'reviewed_by', 'reviewed_at',
    ];

    protected $hidden = ['internal_notes'];

    protected function casts(): array
    {
        return ['requested_changes' => 'array', 'reviewed_at' => 'datetime'];
    }
}
