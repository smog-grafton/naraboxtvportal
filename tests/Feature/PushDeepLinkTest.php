<?php

namespace Tests\Feature;

use App\Support\PushDeepLink;
use PHPUnit\Framework\TestCase;

class PushDeepLinkTest extends TestCase
{
    public function test_it_builds_supported_deep_links(): void
    {
        $this->assertSame('app://movie/42', PushDeepLink::build('movie', 42));
        $this->assertSame('app://tv-show/7', PushDeepLink::build('tv_show', 7));
        $this->assertSame('app://news/screen-rant-style-review', PushDeepLink::build('article', 'screen-rant-style-review'));
        $this->assertSame('app://live/9', PushDeepLink::build('live', 9));
        $this->assertSame('app://vj/3', PushDeepLink::build('vj', 3));
    }

    public function test_it_generates_mobile_payload_data_from_deep_links(): void
    {
        $this->assertSame([
            'deep_link' => 'app://movie/42',
            'type' => 'movie',
            'media_type' => 'MOVIE',
            'media_id' => '42',
        ], PushDeepLink::payloadData('app://movie/42'));

        $this->assertSame([
            'deep_link' => 'app://news/review-slug',
            'type' => 'article',
            'article_slug' => 'review-slug',
        ], PushDeepLink::payloadData('app://news/review-slug'));
    }
}
