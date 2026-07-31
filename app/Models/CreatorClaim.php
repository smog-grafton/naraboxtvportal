<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreatorClaim extends Model
{
    protected $fillable = [
        'user_id', 'creator_application_id', 'claimable_type', 'claimable_id',
        'status', 'legal_name', 'creator_name', 'phone_number', 'email',
        'official_social_url', 'telegram_url', 'youtube_url', 'facebook_url',
        'tiktok_url', 'existing_business_contact', 'sample_work_url',
        'relationship_explanation', 'verification_method', 'challenge_phrase',
        'challenge_code_hash', 'challenge_expires_at', 'review_notes',
        'rejection_reason', 'reviewed_by', 'submitted_at', 'reviewed_at',
    ];

    protected $hidden = ['challenge_code_hash'];

    protected function casts(): array
    {
        return [
            'challenge_expires_at' => 'datetime',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(CreatorApplication::class, 'creator_application_id');
    }

    public function claimable()
    {
        return $this->morphTo();
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(CreatorVerificationEvidence::class);
    }

    public function informationRequests(): HasMany
    {
        return $this->hasMany(CreatorInformationRequest::class);
    }
}
