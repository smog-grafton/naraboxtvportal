<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreatorInformationResponse extends Model
{
    protected $fillable = [
        'information_request_id', 'user_id', 'response_text', 'evidence_id',
        'status', 'review_feedback', 'reviewed_by', 'submitted_at', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(CreatorInformationRequest::class, 'information_request_id');
    }

    public function evidence(): BelongsTo
    {
        return $this->belongsTo(CreatorVerificationEvidence::class, 'evidence_id');
    }
}
