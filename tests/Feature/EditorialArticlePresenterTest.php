<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ArticleBlock;
use App\Models\EditorialCategory;
use App\Models\Movie;
use App\Models\TVShow;
use App\Models\User;
use App\Models\VJ;
use App\Support\EditorialArticlePresenter;
use Illuminate\Support\Collection;
use Tests\TestCase;

class EditorialArticlePresenterTest extends TestCase
{
    public function test_summary_returns_canonical_and_legacy_article_fields(): void
    {
        $article = $this->makeArticle();

        $summary = app(EditorialArticlePresenter::class)->summary($article);

        $this->assertSame(44, $summary['id']);
        $this->assertSame('the-raider-review', $summary['slug']);
        $this->assertSame('review', $summary['postType']);
        $this->assertSame('The Raider Review', $summary['title']);
        $this->assertSame('A grounded action review.', $summary['excerpt']);
        $this->assertSame('Nara Features', $summary['category']);
        $this->assertSame('Nara Features', $summary['primaryCategory']['name']);
        $this->assertSame('nara-features', $summary['primaryCategory']['slug']);
        $this->assertSame('Editorial Lead', $summary['author']);
        $this->assertStringStartsWith('2026-04-18', (string) $summary['publishedAt']);
        $this->assertStringStartsWith('2026-04-18', (string) $summary['published_at']);
        $this->assertSame('Apr 18, 2026', $summary['date']);
        $this->assertSame(8.7, $summary['score']);
        $this->assertSame('Lean, stylish, and worth the ticket.', $summary['verdict']);
        $this->assertSame('https://cdn.example.com/raider-og.jpg', $summary['thumbnail']);
        $this->assertSame('https://cdn.example.com/raider-og.jpg', $summary['image']);
        $this->assertSame(['review', 'action'], $summary['tags']);
    }

    public function test_detail_returns_blocks_legacy_content_and_linked_entities(): void
    {
        $article = $this->makeArticle();
        $article->setRelation('blocks', $this->makeBlocks());

        $detail = app(EditorialArticlePresenter::class)->detail($article);

        $this->assertCount(6, $detail['blocks']);
        $this->assertSame('rich_text', $detail['blocks'][0]['type']);
        $this->assertSame('<p>Opening paragraph.</p>', $detail['blocks'][0]['html']);
        $this->assertSame('quote', $detail['blocks'][1]['type']);
        $this->assertSame('A sharp quote.', $detail['blocks'][1]['value']);
        $this->assertSame('Editor Desk', $detail['blocks'][1]['author']);
        $this->assertSame('cta', $detail['blocks'][5]['type']);
        $this->assertSame('Watch the trailer and compare notes.', $detail['blocks'][5]['value']);
        $this->assertSame('Watch Now', $detail['blocks'][5]['label']);
        $this->assertSame('/movies/the-raider', $detail['blocks'][5]['url']);
        $this->assertStringContainsString('Opening paragraph.', (string) $detail['content']);
        $this->assertStringContainsString('A sharp quote.', (string) $detail['content']);
        $this->assertSame(['Tighter pacing than most genre fare.'], $detail['pros']);
        $this->assertSame(['A thin villain arc.'], $detail['cons']);
        $this->assertSame('The Raider', $detail['relatedMovie']['title']);
        $this->assertSame('Street Signals', $detail['relatedTvShow']['title']);
        $this->assertSame('VJ Asha', $detail['relatedVj']['name']);
        $this->assertSame('The Raider', $detail['reviewTarget']['title']);
        $this->assertSame('Review', $detail['seo']['schemaType']);
        $this->assertSame('https://example.test/news/the-raider-review', $detail['seo']['canonical']);
    }

    private function makeArticle(): Article
    {
        config()->set('app.url', 'https://portal.example.test');

        $author = new User([
            'id' => 7,
            'name' => 'Editorial Lead',
            'email' => 'lead@example.test',
        ]);

        $category = new EditorialCategory([
            'id' => 3,
            'name' => 'Nara Features',
            'slug' => 'nara-features',
            'color' => 'emerald',
        ]);

        $movie = new Movie([
            'id' => 10,
            'title' => 'The Raider',
            'slug' => 'the-raider',
            'thumbnail' => 'movies/the-raider.jpg',
            'backdrop' => 'movies/the-raider-backdrop.jpg',
            'rating' => 8.4,
        ]);

        $tvShow = new TVShow([
            'id' => 11,
            'title' => 'Street Signals',
            'slug' => 'street-signals',
            'thumbnail' => 'shows/street-signals.jpg',
            'backdrop' => 'shows/street-signals-backdrop.jpg',
            'rating' => 7.9,
        ]);

        $vj = new VJ([
            'id' => 12,
            'name' => 'VJ Asha',
            'slug' => 'vj-asha',
            'image' => 'vjs/asha.jpg',
            'banner' => 'vjs/asha-banner.jpg',
            'rating' => 4.8,
            'bio' => 'Known for clean, fast narration.',
        ]);

        $article = new Article([
            'id' => 44,
            'slug' => 'the-raider-review',
            'title' => 'The Raider Review',
            'excerpt' => 'A grounded action review.',
            'author' => 'Editorial Lead',
            'post_type' => 'review',
            'category' => 'Nara Features',
            'date' => '2026-04-18 14:00:00',
            'score' => 8.7,
            'verdict' => 'Lean, stylish, and worth the ticket.',
            'og_image' => 'https://cdn.example.com/raider-og.jpg',
            'seo_title' => 'The Raider Review | NaraBox TV',
            'seo_description' => 'A spoiler-light review of The Raider.',
            'review_target_type' => 'movie',
            'review_target_id' => 10,
            'pros' => ['Tighter pacing than most genre fare.'],
            'cons' => ['A thin villain arc.'],
            'is_top_news' => true,
        ]);

        $article->updated_at = now();
        $article->setRelation('authorUser', $author);
        $article->setRelation('primaryCategory', $category);
        $article->setRelation('movie', $movie);
        $article->setRelation('tvShow', $tvShow);
        $article->setRelation('vj', $vj);
        $article->setRelation('tags', new Collection([
            (object) ['tag' => 'review'],
            (object) ['tag' => 'action'],
        ]));

        return $article;
    }

    private function makeBlocks(): Collection
    {
        $movie = new Movie([
            'id' => 10,
            'title' => 'The Raider',
            'slug' => 'the-raider',
            'thumbnail' => 'movies/the-raider.jpg',
            'backdrop' => 'movies/the-raider-backdrop.jpg',
            'rating' => 8.4,
        ]);

        $tvShow = new TVShow([
            'id' => 11,
            'title' => 'Street Signals',
            'slug' => 'street-signals',
            'thumbnail' => 'shows/street-signals.jpg',
            'backdrop' => 'shows/street-signals-backdrop.jpg',
            'rating' => 7.9,
        ]);

        $vj = new VJ([
            'id' => 12,
            'name' => 'VJ Asha',
            'slug' => 'vj-asha',
            'image' => 'vjs/asha.jpg',
            'banner' => 'vjs/asha-banner.jpg',
            'rating' => 4.8,
            'bio' => 'Known for clean, fast narration.',
        ]);

        return new Collection([
            new ArticleBlock([
                'type' => 'rich_text',
                'value' => '<p>Opening paragraph.</p>',
            ]),
            new ArticleBlock([
                'type' => 'quote',
                'value' => '<p>A sharp quote.</p>',
                'author' => 'Editor Desk',
                'author_title' => 'Senior Critic',
            ]),
            tap(new ArticleBlock([
                'type' => 'movie_embed',
            ]), fn (ArticleBlock $block) => $block->setRelation('movie', $movie)),
            tap(new ArticleBlock([
                'type' => 'tv_show_embed',
            ]), fn (ArticleBlock $block) => $block->setRelation('tvShow', $tvShow)),
            tap(new ArticleBlock([
                'type' => 'vj_embed',
            ]), fn (ArticleBlock $block) => $block->setRelation('vj', $vj)),
            new ArticleBlock([
                'type' => 'cta',
                'value' => 'Watch the trailer and compare notes.',
                'cta_label' => 'Watch Now',
                'cta_url' => '/movies/the-raider',
            ]),
        ]);
    }
}
