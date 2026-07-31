<?php

namespace Tests\Feature;

use App\Models\Movie;
use App\Services\MediaSourceSelectionService;
use App\Services\NbxVideoSourceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MediaSourceReliabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_hls_becomes_primary_and_mp4_remains_bounded_fallback(): void
    {
        Http::fake([
            'https://objects.example/job/hls/master.m3u8' => Http::response(
                "#EXTM3U\n#EXT-X-VERSION:3\n#EXTINF:6,\nsegment-000.ts\n",
                200,
                ['Content-Type' => 'application/vnd.apple.mpegurl'],
            ),
            'https://objects.example/job/hls/segment-000.ts' => Http::response(
                'segment-bytes',
                206,
                ['Content-Type' => 'video/mp2t'],
            ),
        ]);

        $movie = Movie::factory()->create(['is_free' => true]);
        $source = app(NbxVideoSourceService::class)->upsertFromDiscoveryPayload($movie, [
            'nbx_job_id' => 'job-health-1',
            'asset_id' => '019f9b27-4c11-7e59-b4cf-43afaf632bf9',
            'status' => 'completed',
            'storage_target' => 'contabo',
            'faststart_mp4_url' => 'https://objects.example/job/faststart/movie.mp4',
            'hls_master_url' => 'https://objects.example/job/hls/master.m3u8',
            'outputs' => [
                [
                    'role' => 'playback_progressive',
                    'url' => 'https://objects.example/job/faststart/movie.mp4',
                    'verified' => true,
                ],
                [
                    'role' => 'hls_master',
                    'url' => 'https://objects.example/job/hls/master.m3u8',
                    'verified' => true,
                ],
            ],
        ], [
            'is_primary' => true,
            'is_active' => true,
        ]);

        $hls = $movie->videoSources()->where('media_role', 'hls_master')->firstOrFail();
        $source->refresh();

        $this->assertTrue((bool) $hls->is_primary);
        $this->assertSame('healthy', $hls->health_status);
        $this->assertNotNull($hls->verified_at);
        $this->assertFalse((bool) $source->is_primary);

        $candidates = app(MediaSourceSelectionService::class)->candidatesFor($movie, 'web');
        $this->assertSame([$hls->id, $source->id], $candidates->pluck('id')->all());
        $this->assertLessThanOrEqual(5, $candidates->count());
    }

    public function test_invalid_hls_never_displaces_existing_mp4_primary(): void
    {
        Http::fake([
            'https://objects.example/job/hls/master.m3u8' => Http::response(
                '<html>not found</html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        $movie = Movie::factory()->create(['is_free' => true]);
        $mp4 = app(NbxVideoSourceService::class)->upsertFromDiscoveryPayload($movie, [
            'nbx_job_id' => 'job-health-2',
            'status' => 'completed',
            'storage_target' => 'contabo',
            'faststart_mp4_url' => 'https://objects.example/job/faststart/movie.mp4',
            'hls_master_url' => 'https://objects.example/job/hls/master.m3u8',
        ], [
            'is_primary' => true,
            'is_active' => true,
        ]);

        $hls = $movie->videoSources()->where('media_role', 'hls_master')->firstOrFail();

        $this->assertTrue((bool) $mp4->fresh()->is_primary);
        $this->assertFalse((bool) $hls->is_primary);
        $this->assertSame('invalid', $hls->health_status);
        $this->assertFalse(
            app(MediaSourceSelectionService::class)->candidatesFor($movie, 'web')->contains('id', $hls->id)
        );
    }

    public function test_hls_master_is_verified_through_a_child_playlist_and_media_segment(): void
    {
        Http::fake([
            'https://objects.example/job/hls/master.m3u8' => Http::response(
                "#EXTM3U\n#EXT-X-STREAM-INF:BANDWIDTH=800000\n480p/index.m3u8\n",
                200,
                ['Content-Type' => 'application/vnd.apple.mpegurl'],
            ),
            'https://objects.example/job/hls/480p/index.m3u8' => Http::response(
                "#EXTM3U\n#EXTINF:6,\nsegment-000.ts\n",
                200,
                ['Content-Type' => 'application/vnd.apple.mpegurl'],
            ),
            'https://objects.example/job/hls/480p/segment-000.ts' => Http::response(
                'segment-bytes',
                206,
                ['Content-Type' => 'video/mp2t'],
            ),
        ]);

        $movie = Movie::factory()->create(['is_free' => true]);
        app(NbxVideoSourceService::class)->upsertFromDiscoveryPayload($movie, [
            'nbx_job_id' => 'job-nested-hls-health',
            'status' => 'completed',
            'storage_target' => 'contabo',
            'faststart_mp4_url' => 'https://objects.example/job/faststart/movie.mp4',
            'hls_master_url' => 'https://objects.example/job/hls/master.m3u8',
        ], [
            'is_primary' => true,
            'is_active' => true,
        ]);

        $hls = $movie->videoSources()->where('media_role', 'hls_master')->firstOrFail();

        $this->assertSame('healthy', $hls->health_status);
        $this->assertTrue((bool) $hls->is_primary);
        Http::assertSentCount(3);
    }

    public function test_fresh_hls_health_result_is_reused_during_repeated_syncs(): void
    {
        Http::fake([
            'https://objects.example/cache/hls/master.m3u8' => Http::response(
                "#EXTM3U\n#EXTINF:6,\nsegment-000.ts\n",
                200,
                ['Content-Type' => 'application/vnd.apple.mpegurl'],
            ),
            'https://objects.example/cache/hls/segment-000.ts' => Http::response(
                'segment-bytes',
                206,
                ['Content-Type' => 'video/mp2t'],
            ),
        ]);

        $movie = Movie::factory()->create(['is_free' => true]);
        $payload = [
            'nbx_job_id' => 'job-health-cache',
            'status' => 'completed',
            'storage_target' => 'contabo',
            'faststart_mp4_url' => 'https://objects.example/cache/faststart/movie.mp4',
            'hls_master_url' => 'https://objects.example/cache/hls/master.m3u8',
        ];

        app(NbxVideoSourceService::class)->upsertFromDiscoveryPayload($movie, $payload, [
            'is_primary' => true,
            'is_active' => true,
        ]);
        app(NbxVideoSourceService::class)->upsertFromDiscoveryPayload($movie, $payload, [
            'is_primary' => true,
            'is_active' => true,
        ]);

        Http::assertSentCount(2);
    }

    public function test_source_policy_excludes_404_primary_and_keeps_server_separate_from_quality(): void
    {
        $movie = Movie::factory()->create(['is_free' => true]);
        $broken = $movie->videoSources()->create([
            'type' => 'contabo_object_storage',
            'url' => 'https://objects.example/missing.mp4',
            'quality' => '720p',
            'format' => 'mp4',
            'media_role' => 'playback_progressive',
            'server_key' => 'contabo',
            'source_group' => 'contabo:movie',
            'health_status' => 'unreachable',
            'last_http_status' => 404,
            'is_primary' => true,
            'is_active' => true,
        ]);
        $healthy = $movie->videoSources()->create([
            'type' => 'nbx-engine',
            'url' => 'https://objects.example/hls/master.m3u8',
            'quality' => '720p',
            'format' => 'm3u8',
            'media_role' => 'hls_master',
            'server_key' => 'nbx',
            'source_group' => 'job-3',
            'health_status' => 'healthy',
            'verified_at' => now(),
            'is_primary' => false,
            'is_active' => true,
        ]);

        $candidate = app(MediaSourceSelectionService::class)->candidatesFor($movie, 'web')->firstOrFail();
        $payload = app(MediaSourceSelectionService::class)->toCandidate($candidate, true);

        $this->assertSame($healthy->id, $candidate->id);
        $this->assertNotSame($broken->id, $candidate->id);
        $this->assertSame('nbx', $payload['server']);
        $this->assertSame('auto', $payload['quality']);
        $this->assertSame('hls_master', $payload['role']);
    }

    public function test_active_sources_work_without_a_configured_primary_and_hls_is_tried_first(): void
    {
        $movie = Movie::factory()->create(['is_free' => true]);
        $mp4 = $movie->videoSources()->create([
            'type' => 'nbx-engine',
            'url' => 'https://objects.example/movie/faststart/movie.mp4',
            'quality' => '480p',
            'format' => 'mp4',
            'health_status' => 'unknown',
            'is_primary' => false,
            'is_active' => true,
        ]);
        $hls = $movie->videoSources()->create([
            'type' => 'nbx-engine',
            'url' => 'https://objects.example/movie/hls/master.m3u8',
            'quality' => 'auto',
            'format' => 'm3u8',
            'health_status' => 'unknown',
            'is_primary' => false,
            'is_active' => true,
        ]);

        $candidates = app(MediaSourceSelectionService::class)->candidatesFor($movie, 'web');
        $payload = $candidates
            ->map(fn ($source, int $index) => app(MediaSourceSelectionService::class)->toCandidate($source, $index === 0));

        $this->assertSame([$hls->id, $mp4->id], $candidates->pluck('id')->all());
        $this->assertTrue($payload->first()['isPrimary']);
        $this->assertFalse($payload->first()['configured_primary']);
        $this->assertFalse((bool) $hls->fresh()->is_primary);
        $this->assertFalse((bool) $mp4->fresh()->is_primary);
    }

    public function test_contabo_account_namespace_url_is_backfilled_to_a_stable_object_key(): void
    {
        config()->set('services.contabo_object_storage.endpoint', 'https://usc1.contabostorage.com');
        config()->set('services.contabo_object_storage.bucket', 'nbx');
        config()->set('services.contabo_object_storage.public_url', 'https://usc1.contabostorage.com/nbx');
        config()->set('services.contabo_object_storage.disk', 'contabo');
        $movie = Movie::factory()->create(['is_free' => true]);
        $source = $movie->videoSources()->create([
            'type' => 'url',
            'url' => 'https://usc1.contabostorage.com/d052ede4e40a478d92ab1a7ad3f1e435:nbx/videos/movies/1431/movie.mp4',
            'quality' => '480p',
            'format' => 'mp4',
            'is_primary' => false,
            'is_active' => true,
        ]);

        app(MediaSourceSelectionService::class)->hydrateIdentity($source);
        $source->refresh();

        $this->assertSame('contabo', $source->storage_disk);
        $this->assertSame('nbx', $source->storage_bucket);
        $this->assertSame('videos/movies/1431/movie.mp4', $source->storage_object_key);
    }

    public function test_media_source_audit_dry_run_does_not_backfill_or_mutate_source_identity(): void
    {
        config()->set('services.contabo_object_storage.endpoint', 'https://usc1.contabostorage.com');
        config()->set('services.contabo_object_storage.bucket', 'nbx');
        config()->set('services.contabo_object_storage.public_url', 'https://usc1.contabostorage.com/nbx');
        $movie = Movie::factory()->create(['is_free' => true]);
        $source = $movie->videoSources()->create([
            'type' => 'url',
            'url' => 'https://usc1.contabostorage.com/account:nbx/videos/movies/1431/movie.mp4',
            'quality' => '480p',
            'format' => 'mp4',
            'is_primary' => false,
            'is_active' => true,
        ]);

        Artisan::call('media:sources-audit', ['--dry-run' => true, '--limit' => 20]);

        $this->assertNull($source->fresh()->storage_object_key);
        $this->assertStringContainsString('Contabo identity not backfilled', Artisan::output());
    }

    public function test_media_source_audit_repairs_duplicate_primary_flags_without_touching_fallbacks(): void
    {
        $movie = Movie::factory()->create(['is_free' => true]);
        $mp4 = $movie->videoSources()->create([
            'type' => 'url',
            'url' => 'https://objects.example/movie/faststart/movie.mp4',
            'quality' => '480p',
            'format' => 'mp4',
            'media_role' => 'faststart_mp4',
            'health_status' => 'unknown',
            'is_primary' => true,
            'is_active' => true,
        ]);
        $hls = $movie->videoSources()->create([
            'type' => 'url',
            'url' => 'https://objects.example/movie/hls/master.m3u8',
            'quality' => 'auto',
            'format' => 'm3u8',
            'media_role' => 'hls_master',
            'health_status' => 'unknown',
            'is_primary' => true,
            'is_active' => true,
        ]);

        Artisan::call('media:sources-audit', ['--dry-run' => true, '--limit' => 20]);

        $this->assertTrue((bool) $mp4->fresh()->is_primary);
        $this->assertTrue((bool) $hls->fresh()->is_primary);
        $this->assertStringContainsString('multiple primary sources', Artisan::output());

        Artisan::call('media:sources-audit', ['--repair' => true, '--limit' => 20]);

        $this->assertFalse((bool) $mp4->fresh()->is_primary);
        $this->assertTrue((bool) $hls->fresh()->is_primary);
        $this->assertTrue((bool) $mp4->fresh()->is_active);
        $this->assertTrue((bool) $hls->fresh()->is_active);
        $this->assertSame(
            'media_sources_audit_primary_dedupe',
            $hls->fresh()->metadata['primary_selection_reason'] ?? null,
        );
    }

    public function test_legacy_tele_ob_row_uses_fetched_faststart_url_instead_of_telegram_provenance(): void
    {
        $movie = Movie::factory()->create(['is_free' => true]);
        $source = $movie->videoSources()->create([
            'type' => 'tele_ob',
            'url' => 'https://t.me/naraboxtvcom/159',
            'file_path' => null,
            'quality' => '480p',
            'format' => 'mp4',
            'health_status' => 'unreachable',
            'last_health_check_at' => now(),
            'is_primary' => true,
            'is_active' => true,
            'metadata' => [
                'provider' => 'nbx_engine',
                'fetch_status' => 'partially_completed',
                'telegram_url' => 'https://t.me/naraboxtvcom/159',
                'health_checked_url' => 'https://t.me/naraboxtvcom/159',
                'mp4_play_url' => 'https://objects.example/videos/job/faststart/movie_play.mp4',
            ],
        ]);

        $selection = app(MediaSourceSelectionService::class);
        $candidate = $selection->candidatesFor($movie, 'web')->firstOrFail();
        $payload = $selection->toCandidate($candidate, true);

        $this->assertSame($source->id, $candidate->id);
        $this->assertSame('faststart_mp4', $candidate->media_role);
        $this->assertSame(
            'https://objects.example/videos/job/faststart/movie_play.mp4',
            $payload['url']
        );
        $this->assertSame('unknown', $payload['health_status']);
        $this->assertNull($payload['verified_at']);
        $this->assertStringNotContainsString('t.me/', $payload['url']);

        $this->withHeader(config('api.header', 'X-API-KEY'), (string) config('api.key'))
            ->getJson("/api/v1/player/{$movie->id}?media_type=MOVIE")
            ->assertOk()
            ->assertJsonPath(
                'preferred_source.url',
                'https://objects.example/videos/job/faststart/movie_play.mp4'
            )
            ->assertJsonPath(
                'videoUrl',
                'https://objects.example/videos/job/faststart/movie_play.mp4'
            )
            ->assertJsonPath(
                'preferred_source.health_status',
                'unknown'
            );
    }

    public function test_partially_completed_import_without_a_playable_artifact_is_not_a_candidate(): void
    {
        $movie = Movie::factory()->create(['is_free' => true]);
        $movie->videoSources()->create([
            'type' => 'tele_ob',
            'url' => 'https://t.me/naraboxtvcom/160',
            'quality' => '480p',
            'format' => 'mp4',
            'health_status' => 'unknown',
            'is_primary' => true,
            'is_active' => true,
            'metadata' => [
                'provider' => 'nbx_engine',
                'fetch_status' => 'partially_completed',
                'telegram_url' => 'https://t.me/naraboxtvcom/160',
            ],
        ]);

        $this->assertTrue(
            app(MediaSourceSelectionService::class)->candidatesFor($movie, 'web')->isEmpty()
        );

        $movie->forceFill(['video_url' => 'https://t.me/naraboxtvcom/160'])->save();
        $this->withHeader(config('api.header', 'X-API-KEY'), (string) config('api.key'))
            ->getJson("/api/v1/player/{$movie->id}?media_type=MOVIE")
            ->assertNotFound()
            ->assertJsonPath('requiresVideoSource', true);
    }

    public function test_signed_nbx_storage_plan_deactivates_source_before_deletion_and_promotes_fallback(): void
    {
        config()->set('services.nbx_engine.webhook_secret', 'storage-test-secret');
        $movie = Movie::factory()->create(['is_free' => true]);
        $target = $movie->videoSources()->create([
            'type' => 'contabo_object_storage',
            'url' => 'https://objects.example/direct/movie.mp4',
            'quality' => '720p',
            'format' => 'mp4',
            'media_role' => 'faststart_mp4',
            'server_key' => 'contabo',
            'health_status' => 'healthy',
            'verified_at' => now(),
            'is_primary' => true,
            'is_active' => true,
        ]);
        $fallback = $movie->videoSources()->create([
            'type' => 'nbx-engine',
            'url' => 'https://objects.example/backup/movie.mp4',
            'quality' => '720p',
            'format' => 'mp4',
            'media_role' => 'faststart_mp4',
            'server_key' => 'nbx',
            'health_status' => 'healthy',
            'verified_at' => now(),
            'is_primary' => false,
            'is_active' => true,
        ]);
        $payload = [
            'event' => 'storage.deletion_planned',
            'phase' => 'planned',
            'portal_source_id' => $target->id,
            'portal_sourceable_type' => $target->sourceable_type,
            'portal_sourceable_id' => $target->sourceable_id,
            'media_role' => 'faststart_mp4',
            'deleted_object_keys' => ['videos/direct/movie.mp4'],
        ];
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $timestamp = (string) time();

        $this->call('POST', '/api/v1/nbx/storage-events', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_NBX_TIMESTAMP' => $timestamp,
            'HTTP_X_NBX_SIGNATURE' => 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, 'storage-test-secret'),
        ], $body)
            ->assertOk()
            ->assertJsonPath('fallback_source_id', $fallback->id)
            ->assertJsonPath('phase', 'planned');

        $this->assertFalse((bool) $target->fresh()->is_active);
        $this->assertFalse((bool) $target->fresh()->is_primary);
        $this->assertNull($target->fresh()->deleted_from_storage_at);
        $this->assertTrue((bool) $fallback->fresh()->is_primary);
    }
}
