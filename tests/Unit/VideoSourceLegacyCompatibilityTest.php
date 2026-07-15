<?php

namespace Tests\Unit;

use App\Models\VideoSource;
use PHPUnit\Framework\TestCase;

class VideoSourceLegacyCompatibilityTest extends TestCase
{
    public function test_fetched_url_only_source_resolves_full_url(): void
    {
        $source = new VideoSource([
            'type' => 'fetched',
            'url' => 'https://cdn.example.test/videos/legacy-fetched.mp4',
            'file_path' => null,
            'format' => 'mp4',
            'is_active' => true,
        ]);

        $this->assertSame('https://cdn.example.test/videos/legacy-fetched.mp4', $source->full_url);
    }

    public function test_legacy_contabo_source_resolves_public_url_metadata(): void
    {
        $source = new VideoSource([
            'type' => 'contabo',
            'url' => null,
            'file_path' => null,
            'format' => 'mp4',
            'is_active' => true,
            'metadata' => [
                'public_url' => 'https://usc1.contabostorage.com/bucket/videos/old-contabo.mp4',
            ],
        ]);

        $this->assertSame('https://usc1.contabostorage.com/bucket/videos/old-contabo.mp4', $source->full_url);
    }

    public function test_nbx_source_prefers_mp4_before_hls_for_full_url(): void
    {
        $source = new VideoSource([
            'type' => 'nbx-engine',
            'format' => 'hls',
            'is_active' => true,
            'metadata' => [
                'mp4_play_url' => 'https://usc1.contabostorage.com/bucket/videos/faststart.mp4',
                'hls_master_url' => 'https://usc1.contabostorage.com/bucket/videos/hls/master.m3u8',
            ],
        ]);

        $this->assertSame('https://usc1.contabostorage.com/bucket/videos/faststart.mp4', $source->full_url);
    }
}
