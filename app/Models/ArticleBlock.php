<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleBlock extends Model
{
    protected $fillable = [
        'article_id',
        'type',
        'value',
        'caption',
        'alt_text',
        'author',
        'author_title',
        'gallery_images',
        'movie_id',
        'tv_show_id',
        'vj_id',
        'cta_label',
        'cta_url',
        'order',
    ];

    protected function casts(): array
    {
        return [
            'gallery_images' => 'array',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
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
