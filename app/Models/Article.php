<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Article extends Model
{
    protected $fillable = [
        'post_type',
        'title',
        'slug',
        'excerpt',
        'author',
        'author_user_id',
        'image',
        'video_url',
        'category',
        'primary_category_id',
        'movie_id',
        'tv_show_id',
        'vj_id',
        'review_target_type',
        'review_target_id',
        'score',
        'verdict',
        'pros',
        'cons',
        'seo_title',
        'seo_description',
        'og_image',
        'date',
        'is_top_news',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'score' => 'decimal:1',
            'pros' => 'array',
            'cons' => 'array',
            'is_top_news' => 'boolean',
            'is_published' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Article $article) {
            if (blank($article->slug) && filled($article->title)) {
                $article->slug = Str::slug($article->title);
            }

            if (blank($article->post_type)) {
                $article->post_type = 'news';
            }

            if ($article->author_user_id && blank($article->author)) {
                $article->author = User::query()->find($article->author_user_id)?->name;
            }

            if ($article->primary_category_id) {
                $article->category = EditorialCategory::query()
                    ->find($article->primary_category_id)?->name ?? $article->category;
            }
        });
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(ArticleBlock::class)->orderBy('order');
    }

    public function tags(): HasMany
    {
        return $this->hasMany(ArticleTag::class);
    }

    public function authorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }

    public function primaryCategory(): BelongsTo
    {
        return $this->belongsTo(EditorialCategory::class, 'primary_category_id');
    }

    public function movie(): BelongsTo
    {
        return $this->belongsTo(Movie::class);
    }

    public function tvShow(): BelongsTo
    {
        return $this->belongsTo(TVShow::class, 'tv_show_id');
    }

    public function vj(): BelongsTo
    {
        return $this->belongsTo(VJ::class);
    }
}
