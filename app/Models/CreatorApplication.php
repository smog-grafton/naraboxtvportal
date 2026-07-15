<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreatorApplication extends Model
{
    protected $fillable = [
        'user_id',
        'creator_type',
        'display_name',
        'bio',
        'profile_image',
        'genres',
        'status',
        'rejection_reason',
        'admin_notes',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'genres' => 'array',
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

    public function isPending(): bool
    {
        return $this->status === 'pending';
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
        return $this->status === 'needs_changes';
    }

    public function canBeEdited(): bool
    {
        return $this->status === 'needs_changes';
    }

    public function markUnderReview(): void
    {
        $this->update(['status' => 'under_review']);
    }

    public function approve(User $admin): void
    {
        $this->update([
            'status' => 'approved',
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
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
            'status' => 'needs_changes',
            'rejection_reason' => $notes,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);
    }
}
