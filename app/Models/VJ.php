<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class VJ extends Model
{
    use HasFactory;

    protected $table = 'vjs';

    protected $fillable = [
        'name',
        'slug',
        'image',
        'banner',
        'rating',
        'bio',
        'translated_count',
        'is_featured',
        'featured_order',
        'is_active',
        'user_id',
        'is_verified',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'decimal:1',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
            'is_verified' => 'boolean',
        ];
    }

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($vj) {
            if (empty($vj->slug)) {
                $vj->slug = Str::slug($vj->name);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class, 'vj_genre', 'vj_id', 'genre_id');
    }

    public function movies(): HasMany
    {
        return $this->hasMany(Movie::class, 'vj_id');
    }
}
