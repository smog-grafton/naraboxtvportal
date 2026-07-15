<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNotification extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'message',
        'type',
        'image_url',
        'action_url',
        'media_type',
        'media_id',
        'is_global',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'is_global' => 'boolean',
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser(Builder $query, ?User $user): Builder
    {
        return $query->where(function (Builder $notificationQuery) use ($user) {
            $notificationQuery->where('is_global', true);

            if ($user) {
                $notificationQuery->orWhere('user_id', $user->id);
            }
        });
    }
}
