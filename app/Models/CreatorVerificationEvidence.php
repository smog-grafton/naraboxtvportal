<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreatorVerificationEvidence extends Model
{
    protected $table = 'creator_verification_evidence';

    protected $fillable = [
        'user_id', 'creator_application_id', 'creator_claim_id', 'kind',
        'disk', 'path', 'original_filename', 'mime_type', 'size_bytes',
        'status', 'metadata', 'uploaded_at', 'reviewed_at', 'reviewed_by',
        'retention_until', 'deleted_at',
    ];

    protected $hidden = ['disk', 'path'];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'uploaded_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'retention_until' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(CreatorApplication::class, 'creator_application_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function claim(): BelongsTo
    {
        return $this->belongsTo(CreatorClaim::class, 'creator_claim_id');
    }
}
