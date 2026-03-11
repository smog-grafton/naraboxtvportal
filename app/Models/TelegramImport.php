<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramImport extends Model
{
    protected $fillable = [
        'telegram_chat_id',
        'telegram_message_id',
        'telegram_channel',
        'title_guess',
        'vj_guess',
        'episode_guess',
        'cdn_asset_id',
        'cdn_source_id',
        'status',
        'raw_metadata',
    ];

    protected function casts(): array
    {
        return [
            'raw_metadata' => 'array',
        ];
    }

    public function scopeByStatus($query, string $status): void
    {
        $query->where('status', $status);
    }
}
