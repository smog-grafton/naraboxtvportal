<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreatorContentSubmission extends Model
{
    use HasUuids;

    protected $fillable = [
        'id', 'user_id', 'creator_application_id', 'content_type', 'content_id',
        'submission_source', 'processing_status', 'editorial_status',
        'publication_status', 'monetization_status', 'cdn_asset_id',
        'video_source_id', 'processing_job_id', 'progress_percent',
        'processing_stage', 'status_message', 'failure_reason',
        'processing_profile', 'result_metadata', 'retry_count',
        'last_retried_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'processing_profile' => 'array',
            'result_metadata' => 'array',
            'last_retried_at' => 'datetime',
            'completed_at' => 'datetime',
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

    public function content()
    {
        return $this->morphTo();
    }

    public function uploadSessions(): HasMany
    {
        return $this->hasMany(CreatorUploadSession::class, 'submission_id');
    }
}
