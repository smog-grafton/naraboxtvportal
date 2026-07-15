<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunicationLog extends Model
{
    protected $fillable = [
        'communication_campaign_id',
        'communication_recipient_id',
        'user_id',
        'channel',
        'recipient',
        'subject',
        'template_name',
        'status',
        'provider_response',
        'error_message',
        'sent_at',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'provider_response' => 'array',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(CommunicationCampaign::class, 'communication_campaign_id');
    }

    public function recipientRecord(): BelongsTo
    {
        return $this->belongsTo(CommunicationRecipient::class, 'communication_recipient_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
