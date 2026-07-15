<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DmcaNotice extends Model
{
    protected $fillable = [
        'content_type',
        'content_id',
        'reference_number',
        'complainant_name',
        'complainant_email',
        'represented_rightsholder',
        'claim_description',
        'source',
        'affected_url',
        'received_at',
        'reviewed_at',
        'action_taken',
        'status',
        'notes',
        'attachments_json',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'attachments_json' => 'array',
            'received_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

