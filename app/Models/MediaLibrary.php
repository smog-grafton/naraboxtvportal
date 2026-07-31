<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

class MediaLibrary extends Model
{
    protected $table = 'media_libraries';

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'image',
        'banner',
        'bio',
        'is_active',
        'is_verified',
        'is_featured',
        'featured_order',
        'languages',
        'location',
        'official_links',
        'public_email',
        'profile_review_status',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_verified' => 'boolean',
            'is_featured' => 'boolean',
            'languages' => 'array',
            'official_links' => 'array',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (MediaLibrary $library) {
            if (empty($library->slug)) {
                $library->slug = Str::slug($library->name);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function movies(): HasMany
    {
        return $this->hasMany(Movie::class, 'media_library_id');
    }

    public function tvShows(): HasMany
    {
        return $this->hasMany(\App\Models\TVShow::class, 'media_library_id');
    }

    public function claims(): MorphMany
    {
        return $this->morphMany(CreatorClaim::class, 'claimable');
    }

    public function ownershipHistory(): MorphMany
    {
        return $this->morphMany(CreatorProfileOwnershipHistory::class, 'profile');
    }

    public function profileChangeRequests(): MorphMany
    {
        return $this->morphMany(CreatorProfileChangeRequest::class, 'profile');
    }
}
