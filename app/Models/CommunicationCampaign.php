<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunicationCampaign extends Model
{
    protected $fillable = [
        'name',
        'channel',
        'email_template_id',
        'created_by',
        'status',
        'audience_type',
        'send_to_all',
        'marketing_only',
        'filters',
        'recipient_emails',
        'subject_override',
        'body_override',
        'scheduled_at',
        'started_at',
        'completed_at',
        'success_count',
        'failure_count',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'recipient_emails' => 'array',
            'send_to_all' => 'boolean',
            'marketing_only' => 'boolean',
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class, 'email_template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(CommunicationRecipient::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(CommunicationLog::class);
    }
}
