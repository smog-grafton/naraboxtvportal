<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Actor extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'image',
        'role',
        'bio',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($actor) {
            if (empty($actor->slug)) {
                $actor->slug = static::makeUniqueSlug((string) $actor->name);
            }
        });

        static::updating(function ($actor) {
            if (empty($actor->slug)) {
                $actor->slug = static::makeUniqueSlug((string) $actor->name, $actor->id);
            }
        });
    }

    public static function makeUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);

        if ($base === '') {
            $base = 'actor-' . substr(md5($name), 0, 12);
        }

        $slug = $base;
        $suffix = 2;

        while (static::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()
        ) {
            $slug = $base . '-' . $suffix++;
        }

        return $slug;
    }

    /**
     * Get the full URL for the actor's image
     */
    public function getImageUrlAttribute(): ?string
    {
        if (empty($this->image)) {
            return null;
        }

        // If it's already a full URL, return it
        if (str_starts_with($this->image, 'http://') || str_starts_with($this->image, 'https://')) {
            return $this->image;
        }

        // If it's a storage path, return the full URL
        if (Storage::disk('public')->exists($this->image)) {
            return Storage::disk('public')->url($this->image);
        }

        // Fallback to the stored value
        return $this->image;
    }

    public function movies(): BelongsToMany
    {
        return $this->belongsToMany(Movie::class, 'media_actor', 'actor_id', 'media_id')
            ->withPivot('role', 'order')
            ->orderByPivot('order');
    }
}
