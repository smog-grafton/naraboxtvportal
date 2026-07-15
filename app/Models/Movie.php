<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

class Movie extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'thumbnail',
        'backdrop',
        'rating',
        'release_date',
        'category_id',
        'media_type',
        'vj_id',
        'duration',
        'trending_score',
        'is_free',
        'is_premium',
        'video_url',
        'price_rent',
        'price_buy',
        'certificate',
        'country',
        'original_language',
        'language',
        'is_featured',
        'featured_order',
        'download_enabled',
        'is_active',
        // Platform content lifecycle
        'content_status',
        // TMDB fields
        'tmdb_id',
        'imdb_id',
        'original_title',
        'tagline',
        'budget',
        'revenue',
        'status',
        'homepage',
        'popularity',
        'vote_count',
        'production_companies',
        'production_countries',
        'collection_id',
        // Creator ownership
        'media_library_id',
        'cdn_asset_id',
        'publish_status',
    ];

    protected function casts(): array
    {
        return [
            'release_date' => 'date',
            'rating' => 'decimal:1',
            'price_rent' => 'decimal:2',
            'price_buy' => 'decimal:2',
            'is_free' => 'boolean',
            'is_premium' => 'boolean',
            'is_featured' => 'boolean',
            'download_enabled' => 'boolean',
            'is_active' => 'boolean',
            'content_status' => 'string',
            'budget' => 'integer',
            'revenue' => 'integer',
            'popularity' => 'decimal:2',
            'vote_count' => 'integer',
            'production_companies' => 'array',
            'production_countries' => 'array',
        ];
    }

    public function isDmcaRemoved(): bool
    {
        return $this->content_status === 'dmca_removed';
    }

    public function scopePubliclyVisible(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereIn('content_status', ['published']);
    }

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($movie) {
            if (empty($movie->slug)) {
                $base = Str::slug($movie->title);
                // Same movie (title) can exist per VJ; keep slug unique by appending VJ when present
                if ($movie->vj_id) {
                    $vj = $movie->vj ?? VJ::find($movie->vj_id);
                    $base .= '-' . ($vj ? $vj->slug : 'vj-' . $movie->vj_id);
                }
                $movie->slug = static::ensureUniqueSlug($base, null);
            }
        });
    }

    /**
     * Return a slug that is unique in the movies table (optionally ignoring an id for updates).
     */
    protected static function ensureUniqueSlug(string $base, ?int $ignoreId): string
    {
        $slug = $base;
        $query = static::where('slug', $slug);
        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }
        $n = 0;
        while ($query->exists()) {
            $n++;
            $slug = $base . '-' . $n;
            $query = static::where('slug', $slug);
            if ($ignoreId !== null) {
                $query->where('id', '!=', $ignoreId);
            }
        }
        return $slug;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function vj(): BelongsTo
    {
        return $this->belongsTo(VJ::class);
    }

    public function mediaLibrary(): BelongsTo
    {
        return $this->belongsTo(MediaLibrary::class);
    }

    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class, 'media_genre', 'media_id', 'genre_id');
    }

    public function actors(): BelongsToMany
    {
        return $this->belongsToMany(Actor::class, 'media_actor', 'media_id', 'actor_id')
            ->withPivot('role', 'order')
            ->orderByPivot('order');
    }

    public function seasons(): HasMany
    {
        return $this->hasMany(Season::class, 'media_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class, 'media_id');
    }

    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class, 'media_id');
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class, 'media_id');
    }

    public function heroSlide()
    {
        return $this->hasOne(HeroSlide::class, 'media_id');
    }

    public function watchHistory(): HasMany
    {
        return $this->hasMany(WatchHistory::class, 'media_id');
    }

    public function videoSources(): MorphMany
    {
        return $this->morphMany(VideoSource::class, 'sourceable');
    }

    public function downloadSources(): MorphMany
    {
        return $this->morphMany(DownloadSource::class, 'downloadable');
    }

    public function trailers(): MorphMany
    {
        return $this->morphMany(Trailer::class, 'trailerable');
    }

    public function keywords(): MorphMany
    {
        return $this->morphMany(Keyword::class, 'keywordable');
    }

    public function crew(): MorphMany
    {
        return $this->morphMany(Crew::class, 'crewable');
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    public function subtitles(): MorphMany
    {
        return $this->morphMany(Subtitle::class, 'subtitleable');
    }

    public function playbackMarkers(): MorphMany
    {
        return $this->morphMany(PlaybackMarker::class, 'markerable');
    }

    public function dmcaNotices(): HasMany
    {
        return $this->hasMany(DmcaNotice::class, 'content_id')->where('content_type', 'MOVIE');
    }
}
