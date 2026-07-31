<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CreatorApplication extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_INFORMATION_REQUIRED = 'additional_information_required';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'user_id',
        'creator_type',
        'display_name',
        'legal_name',
        'phone_number',
        'public_email',
        'website_url',
        'social_links',
        'application_mode',
        'onboarding_step',
        'claimable_type',
        'claimable_id',
        'bio',
        'profile_image',
        'genres',
        'status',
        'verification_status',
        'challenge_phrase',
        'challenge_code_hash',
        'challenge_expires_at',
        'submitted_at',
        'resubmitted_at',
        'approved_at',
        'suspended_at',
        'suspension_reason',
        'revoked_at',
        'rejection_reason',
        'admin_notes',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'genres' => 'array',
            'social_links' => 'array',
            'challenge_expires_at' => 'datetime',
            'submitted_at' => 'datetime',
            'resubmitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'suspended_at' => 'datetime',
            'revoked_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function claimable()
    {
        return $this->morphTo();
    }

    public function claims(): HasMany
    {
        return $this->hasMany(CreatorClaim::class);
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(CreatorVerificationEvidence::class);
    }

    public function informationRequests(): HasMany
    {
        return $this->hasMany(CreatorInformationRequest::class);
    }

    public function permission(): HasOne
    {
        return $this->hasOne(CreatorPermission::class);
    }

    public function isPending(): bool
    {
        return in_array($this->status, ['pending', self::STATUS_SUBMITTED], true);
    }

    public function isUnderReview(): bool
    {
        return $this->status === 'under_review';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function needsChanges(): bool
    {
        return in_array($this->status, ['needs_changes', self::STATUS_INFORMATION_REQUIRED], true);
    }

    public function canBeEdited(): bool
    {
        return in_array($this->status, [
            self::STATUS_DRAFT,
            'pending',
            'needs_changes',
            self::STATUS_INFORMATION_REQUIRED,
        ], true);
    }

    public function markUnderReview(): void
    {
        $this->update(['status' => 'under_review']);
    }

    public function approve(User $admin): void
    {
        $this->update([
            'status' => self::STATUS_APPROVED,
            'verification_status' => 'verified',
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
            'approved_at' => now(),
        ]);
    }

    public function reject(User $admin, string $reason): void
    {
        $this->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);
    }

    public function requestChanges(User $admin, string $notes): void
    {
        $this->update([
            'status' => self::STATUS_INFORMATION_REQUIRED,
            'rejection_reason' => $notes,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);
    }
}
