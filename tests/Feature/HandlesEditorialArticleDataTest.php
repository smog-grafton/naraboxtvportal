<?php

namespace Tests\Feature;

use App\Filament\Resources\ArticleResource\Pages\HandlesEditorialArticleData;
use Tests\TestCase;

class HandlesEditorialArticleDataTest extends TestCase
{
    public function test_prepare_article_data_allows_missing_linked_content_keys(): void
    {
        $handler = new class
        {
            use HandlesEditorialArticleData;

            public function transform(array $data): array
            {
                return $this->prepareArticleData($data, false);
            }
        };

        $prepared = $handler->transform([
            'post_type' => 'news',
            'title' => 'Local editorial post',
        ]);

        $this->assertArrayHasKey('movie_id', $prepared);
        $this->assertArrayHasKey('tv_show_id', $prepared);
        $this->assertArrayHasKey('vj_id', $prepared);
        $this->assertNull($prepared['movie_id']);
        $this->assertNull($prepared['tv_show_id']);
        $this->assertNull($prepared['vj_id']);
        $this->assertNull($prepared['review_target_type']);
        $this->assertNull($prepared['review_target_id']);
    }

    public function test_prepare_article_data_preserves_rich_text_block_value(): void
    {
        $handler = new class
        {
            use HandlesEditorialArticleData;

            public function transform(array $data): array
            {
                return $this->prepareArticleData($data, false);
            }
        };

        $prepared = $handler->transform([
            'post_type' => 'feature',
            'title' => 'Behind the dub',
            'blocks' => [
                [
                    'type' => 'rich_text',
                    'rich_text_value' => '<p>This paragraph must survive save.</p>',
                    'quote_value' => null,
                    'image_url_value' => null,
                    'cta_value' => null,
                ],
            ],
        ]);

        $this->assertSame('rich_text', $prepared['blocks'][0]['type']);
        $this->assertSame('<p>This paragraph must survive save.</p>', $prepared['blocks'][0]['value']);
        $this->assertArrayNotHasKey('rich_text_value', $prepared['blocks'][0]);
    }
}
