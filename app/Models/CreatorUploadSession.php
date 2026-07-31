<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreatorUploadSession extends Model
{
    use HasUuids;

    protected $fillable = [
        'id', 'submission_id', 'user_id', 'video_source_id', 'token_hash',
        'filename', 'mime_type', 'size_bytes', 'processing_profile',
        'expires_at', 'consumed_at', 'remote_ip',
    ];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'processing_profile' => 'array',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(CreatorContentSubmission::class, 'submission_id');
    }

    public function isUsable(): bool
    {
        return $this->consumed_at === null && $this->expires_at->isFuture();
    }
}
