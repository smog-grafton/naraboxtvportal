<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreatorInformationRequest extends Model
{
    protected $fillable = [
        'user_id', 'creator_application_id', 'creator_claim_id', 'field_label',
        'instructions', 'field_type', 'step_key', 'is_required', 'options',
        'allowed_mime_types', 'max_file_size_kb', 'due_at', 'internal_reason',
        'creator_message', 'status', 'created_by', 'closed_by', 'closed_at',
    ];

    protected $hidden = ['internal_reason'];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'options' => 'array',
            'allowed_mime_types' => 'array',
            'due_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function responses(): HasMany
    {
        return $this->hasMany(CreatorInformationResponse::class, 'information_request_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(CreatorApplication::class, 'creator_application_id');
    }
}
