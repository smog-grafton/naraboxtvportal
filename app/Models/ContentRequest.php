<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentRequest extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'email',
        'title',
        'type',
        'message',
        'status',
        'admin_notes',
        'requested_from',
        'notify_on_status_change',
    ];

    protected function casts(): array
    {
        return [
            'notify_on_status_change' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
