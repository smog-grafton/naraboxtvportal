<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Comment extends Model
{
    protected $fillable = [
        'media_id',
        'user_id',
        'user_name',
        'avatar',
        'text',
        'parent_id',
        'likes',
    ];

    protected function casts(): array
    {
        return [
            'likes' => 'integer',
        ];
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Movie::class, 'media_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(Comment::class, 'parent_id')->orderBy('created_at');
    }
}
