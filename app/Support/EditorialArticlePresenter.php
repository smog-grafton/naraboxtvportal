<?php

namespace App\Support;

use App\Models\Article;
use App\Models\ArticleBlock;
use App\Models\Movie;
use App\Models\TVShow;
use App\Models\VJ;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class EditorialArticlePresenter
{
    public function summary(Article $article): array
    {
        $featuredImage = $this->imageUrl($article->og_image ?: $article->image);
        $publishedAt = $this->isoDate($article->date);
        $primaryCategory = $this->categoryPayload($article);

        return [
            'id' => $article->id,
            'slug' => $article->slug ?: (string) $article->id,
            'postType' => $article->post_type ?: 'news',
            'title' => $article->title,
            'excerpt' => $article->excerpt,
            'thumbnail' => $featuredImage,
            'featuredImage' => $featuredImage,
            'published_at' => $publishedAt,
            'publishedAt' => $publishedAt,
            'updatedAt' => optional($article->updated_at)->toIso8601String(),
            'primaryCategory' => $primaryCategory,
            'author' => $this->authorName($article),
            'authorProfile' => $this->authorProfile($article),
            'score' => $article->score !== null ? (float) $article->score : null,
            'verdict' => $article->verdict,
            'isTopNews' => (bool) $article->is_top_news,
            'tags' => $article->relationLoaded('tags')
                ? $article->tags->pluck('tag')->filter()->values()->all()
                : [],
            'image' => $featuredImage,
            'date' => $this->displayDate($article->date),
            'category' => $primaryCategory['name'] ?? $article->category,
        ];
    }

    public function detail(Article $article): array
    {
        $seoTitle = $article->seo_title ?: "{$article->title} | NaraBox TV News";
        $seoDescription = $article->seo_description ?: $article->excerpt ?: $article->title;
        $ogImage = $this->imageUrl($article->og_image ?: $article->image);

        return array_merge($this->summary($article), [
            'blocks' => $article->relationLoaded('blocks')
                ? $article->blocks->map(fn (ArticleBlock $block) => $this->blockPayload($block))->values()->all()
                : [],
            'content' => $article->relationLoaded('blocks')
                ? $this->legacyContent($article->blocks)
                : null,
            'videoUrl' => $article->video_url,
            'pros' => array_values(array_filter($article->pros ?? [])),
            'cons' => array_values(array_filter($article->cons ?? [])),
            'relatedMovie' => $this->moviePayload($article->movie),
            'relatedTvShow' => $this->tvShowPayload($article->tvShow),
            'relatedVj' => $this->vjPayload($article->vj),
            'reviewTarget' => $this->reviewTargetPayload($article),
            'seo' => [
                'title' => Str::limit($seoTitle, 60, '...'),
                'description' => Str::limit(strip_tags((string) $seoDescription), 160, '...'),
                'ogImage' => $ogImage,
                'canonical' => $this->frontendBaseUrl() . '/news/' . ($article->slug ?: $article->id),
                'schemaType' => $this->schemaType($article),
                'authorUrl' => $this->authorProfile($article)['url'] ?? null,
            ],
        ]);
    }

    private function schemaType(Article $article): string
    {
        return match ($article->post_type) {
            'review' => 'Review',
            'news' => 'NewsArticle',
            default => 'Article',
        };
    }

    private function frontendBaseUrl(): string
    {
        $baseUrl = rtrim((string) config('app.url', ''), '/');

        if (str_contains($baseUrl, 'portal.')) {
            return str_replace('://portal.', '://', $baseUrl);
        }

        return $baseUrl;
    }

    private function authorName(Article $article): string
    {
        return trim((string) ($article->author ?: $article->authorUser?->name ?: 'Nara Editorial Team'));
    }

    private function authorProfile(Article $article): ?array
    {
        if (! $article->authorUser) {
            return null;
        }

        return [
            'id' => $article->authorUser->id,
            'name' => $article->authorUser->name,
            'email' => $article->authorUser->email,
            'url' => null,
        ];
    }

    private function categoryPayload(Article $article): ?array
    {
        if ($article->primaryCategory) {
            return [
                'id' => $article->primaryCategory->id,
                'name' => $article->primaryCategory->name,
                'slug' => $article->primaryCategory->slug,
                'color' => $article->primaryCategory->color,
            ];
        }

        if (blank($article->category)) {
            return null;
        }

        return [
            'id' => null,
            'name' => $article->category,
            'slug' => Str::slug((string) $article->category),
            'color' => null,
        ];
    }

    private function blockPayload(ArticleBlock $block): array
    {
        $canonicalType = $block->type === 'text' ? 'rich_text' : $block->type;

        return match ($canonicalType) {
            'rich_text' => [
                'type' => 'rich_text',
                'html' => $block->value,
                'text' => trim(strip_tags((string) $block->value)),
            ],
            'quote' => [
                'type' => 'quote',
                'value' => trim(strip_tags((string) $block->value)),
                'html' => $block->value,
                'author' => $block->author,
                'authorTitle' => $block->author_title,
            ],
            'image' => [
                'type' => 'image',
                'value' => $this->imageUrl($block->value),
                'caption' => $block->caption,
                'altText' => $block->alt_text,
            ],
            'gallery' => [
                'type' => 'gallery',
                'images' => collect($block->gallery_images ?? [])
                    ->map(fn ($image) => $this->imageUrl(is_array($image) ? ($image['url'] ?? null) : $image))
                    ->filter()
                    ->values()
                    ->all(),
            ],
            'movie_embed' => [
                'type' => 'movie_embed',
                'movie' => $this->moviePayload($block->movie),
            ],
            'tv_show_embed' => [
                'type' => 'tv_show_embed',
                'tvShow' => $this->tvShowPayload($block->tvShow),
            ],
            'vj_embed' => [
                'type' => 'vj_embed',
                'vj' => $this->vjPayload($block->vj),
            ],
            'cta' => [
                'type' => 'cta',
                'value' => trim(strip_tags((string) $block->value)),
                'label' => $block->cta_label,
                'url' => $block->cta_url,
            ],
            default => [
                'type' => $canonicalType,
            ],
        };
    }

    private function legacyContent(Collection $blocks): ?string
    {
        $text = $blocks
            ->map(function (ArticleBlock $block) {
                return match ($block->type) {
                    'text', 'rich_text', 'quote' => trim(strip_tags((string) $block->value)),
                    'cta' => trim(strip_tags((string) $block->value)),
                    default => null,
                };
            })
            ->filter()
            ->implode("\n\n");

        return $text !== '' ? $text : null;
    }

    private function reviewTargetPayload(Article $article): ?array
    {
        return match ($article->review_target_type) {
            'movie' => $this->moviePayload($article->movie),
            'tv_show' => $this->tvShowPayload($article->tvShow),
            'vj' => $this->vjPayload($article->vj),
            default => null,
        };
    }

    private function moviePayload(?Movie $movie): ?array
    {
        if (! $movie) {
            return null;
        }

        return [
            'id' => $movie->id,
            'slug' => $movie->slug,
            'title' => $movie->title,
            'thumbnail' => $this->imageUrl($movie->thumbnail),
            'backdrop' => $this->imageUrl($movie->backdrop),
            'rating' => $movie->rating !== null ? (float) $movie->rating : null,
            'url' => $this->frontendBaseUrl() . '/movies/' . ($movie->slug ?: $movie->id),
        ];
    }

    private function tvShowPayload(?TVShow $tvShow): ?array
    {
        if (! $tvShow) {
            return null;
        }

        return [
            'id' => $tvShow->id,
            'slug' => $tvShow->slug,
            'title' => $tvShow->title,
            'thumbnail' => $this->imageUrl($tvShow->thumbnail),
            'backdrop' => $this->imageUrl($tvShow->backdrop),
            'rating' => $tvShow->rating !== null ? (float) $tvShow->rating : null,
            'url' => $this->frontendBaseUrl() . '/tv-shows/' . ($tvShow->slug ?: $tvShow->id),
        ];
    }

    private function vjPayload(?VJ $vj): ?array
    {
        if (! $vj) {
            return null;
        }

        return [
            'id' => $vj->id,
            'slug' => $vj->slug,
            'name' => $vj->name,
            'image' => $this->imageUrl($vj->image),
            'banner' => $this->imageUrl($vj->banner),
            'bio' => $vj->bio,
            'url' => $this->frontendBaseUrl() . '/vjs/' . ($vj->slug ?: $vj->id),
        ];
    }

    private function imageUrl(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        return asset('storage/' . ltrim($path, '/'));
    }

    private function displayDate(?CarbonInterface $date): ?string
    {
        return $date?->format('M d, Y');
    }

    private function isoDate(?CarbonInterface $date): ?string
    {
        return $date?->copy()->startOfDay()->toIso8601String();
    }
}
