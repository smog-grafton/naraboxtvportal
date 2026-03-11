<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Article extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'author',
        'image',
        'video_url',
        'category',
        'date',
        'is_top_news',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_top_news' => 'boolean',
            'is_published' => 'boolean',
        ];
    }

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($article) {
            if (empty($article->slug)) {
                $article->slug = Str::slug($article->title);
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
}
