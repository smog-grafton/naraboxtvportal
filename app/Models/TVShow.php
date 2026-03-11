<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

class TVShow extends Model
{
    protected $table = 'tv_shows';

    protected $fillable = [
        'title',
        'slug',
        'description',
        'thumbnail',
        'backdrop',
        'rating',
        'release_date',
        'category_id',
        'vj_id',
        'duration',
        'trending_score',
        'is_free',
        'is_premium',
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
        // TMDB fields
        'tmdb_id',
        'imdb_id',
        'original_title',
        'tagline',
        'status',
        'homepage',
        'popularity',
        'vote_count',
        'number_of_seasons',
        'number_of_episodes',
        'networks',
        'production_companies',
        'production_countries',
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
            'popularity' => 'decimal:2',
            'vote_count' => 'integer',
            'number_of_seasons' => 'integer',
            'number_of_episodes' => 'integer',
            'networks' => 'array',
            'production_companies' => 'array',
            'production_countries' => 'array',
        ];
    }

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($tvShow) {
            if (empty($tvShow->slug)) {
                $tvShow->slug = Str::slug($tvShow->title);
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function vj(): BelongsTo
    {
        return $this->belongsTo(VJ::class);
    }

    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class, 'tv_show_genre', 'tv_show_id', 'genre_id');
    }

    public function actors(): BelongsToMany
    {
        return $this->belongsToMany(Actor::class, 'tv_show_actor', 'tv_show_id', 'actor_id')
            ->withPivot('role', 'order')
            ->orderByPivot('order');
    }

    public function seasons(): HasMany
    {
        return $this->hasMany(Season::class, 'tv_show_id')->orderBy('number');
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    public function rentals(): MorphMany
    {
        return $this->morphMany(Rental::class, 'rentable');
    }

    public function purchases(): MorphMany
    {
        return $this->morphMany(Purchase::class, 'purchasable');
    }

    public function videoSources(): MorphMany
    {
        return $this->morphMany(VideoSource::class, 'sourceable');
    }

    public function downloadSources(): MorphMany
    {
        return $this->morphMany(DownloadSource::class, 'downloadable');
    }

    public function heroSlide()
    {
        return $this->hasOne(HeroSlide::class, 'media_id')->where('sourceable_type', TVShow::class);
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

    public function subtitles(): MorphMany
    {
        return $this->morphMany(Subtitle::class, 'subtitleable');
    }
}
