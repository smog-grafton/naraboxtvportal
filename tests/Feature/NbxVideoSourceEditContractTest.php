<?php

namespace Tests\Feature;

use App\Models\Movie;
use App\Models\VideoSource;
use App\Services\NbxVideoSourceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NbxVideoSourceEditContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_ordinary_edit_updates_same_record_without_submitting_an_nbx_job(): void
    {
        Http::fake();
        $source = VideoSource::query()->create([
            'sourceable_type' => Movie::class,
            'sourceable_id' => 999,
            'type' => 'nbx-engine',
            'url' => 'https://objects.example/videos/job/faststart/movie.mp4',
            'file_path' => 'https://objects.example/videos/job/faststart/movie.mp4',
            'quality' => '480p',
            'format' => 'mp4',
            'is_primary' => false,
            'is_active' => true,
            'metadata' => [
                'provider' => 'nbx_engine',
                'nbx_job_id' => 'job-edit-contract',
                'processing_config' => [
                    'input' => ['source_url' => 'https://origin.example/movie.mov'],
                    'last_request' => [
                        'storage_target' => 'contabo',
                        'faststart' => true,
                        'compression' => false,
                        'hls' => ['480p' => false, '720p' => false, '1080p' => false],
                        'allow_downloads' => true,
                        'allow_hls_streaming' => true,
                        'retention_policy' => 'optimized_only',
                        'processing_preset' => 'automatic',
                        'max_resolution' => 720,
                        'crf' => 23,
                        'encoder_preset' => 'medium',
                        'audio_bitrate' => '128k',
                    ],
                ],
            ],
        ]);

        $updated = app(NbxVideoSourceService::class)->updateMetadataOnly($source, [
            'quality' => '720p',
            'format' => 'mp4',
            'is_primary' => true,
            'is_active' => true,
            'nbx_storage_target' => 'contabo',
            'nbx_faststart' => true,
            'nbx_compress_enabled' => false,
            'nbx_hls_480p' => false,
            'nbx_hls_720p' => false,
            'nbx_hls_1080p' => false,
            'nbx_allow_downloads' => true,
            'nbx_allow_hls_streaming' => true,
            'nbx_retention_policy' => 'optimized_only',
            'nbx_processing_preset' => 'automatic',
            'nbx_max_resolution' => '720',
            'nbx_crf' => 23,
            'nbx_encoder_preset' => 'medium',
            'nbx_audio_bitrate' => '128k',
        ]);

        $this->assertSame($source->id, $updated->id);
        $this->assertTrue((bool) $updated->is_primary);
        $this->assertSame('https://objects.example/videos/job/faststart/movie.mp4', $updated->url);
        $this->assertArrayNotHasKey('draft_requires_reprocess', $updated->metadata['processing_config']);
        Http::assertNothingSent();
    }

    public function test_edit_form_hydrates_original_input_and_saved_processing_settings(): void
    {
        $source = new VideoSource([
            'type' => 'nbx-engine',
            'url' => 'https://objects.example/videos/job/faststart/movie.mp4',
            'metadata' => [
                'processing_config' => [
                    'input' => ['source_url' => 'https://origin.example/movie.mkv'],
                    'last_request' => [
                        'storage_target' => 'contabo',
                        'faststart' => true,
                        'compression' => true,
                        'hls' => ['480p' => true, '720p' => false, '1080p' => false],
                        'retention_policy' => 'retain_original',
                        'max_resolution' => 720,
                    ],
                ],
            ],
        ]);

        $form = app(NbxVideoSourceService::class)->hydrateProcessingForm($source);

        $this->assertSame('https://origin.example/movie.mkv', $form['url']);
        $this->assertTrue($form['nbx_compress_enabled']);
        $this->assertTrue($form['nbx_hls_480p']);
        $this->assertSame('retain_original', $form['nbx_retention_policy']);
    }

    public function test_sync_uses_persisted_nbx_source_id_before_mutable_output_url(): void
    {
        config()->set('services.nbx_engine.base_url', 'https://nbx.example');
        $movie = Movie::factory()->create();
        $source = $movie->videoSources()->create([
            'type' => 'nbx-engine',
            'url' => 'https://objects.example/changed-after-retry.mp4',
            'file_path' => 'https://objects.example/changed-after-retry.mp4',
            'quality' => '480p',
            'format' => 'mp4',
            'is_primary' => false,
            'is_active' => true,
            'nbx_asset_id' => '019fa943-a59e-71b7-a57a-5cda96124fe1',
            'processing_job_id' => 'historical-job',
            'metadata' => [
                'provider' => 'nbx_engine',
                'cdn_source_id' => 150,
                'cdn_asset_id' => '019fa943-a59e-71b7-a57a-5cda96124fe1',
                'nbx_job_id' => 'historical-job',
                'fetch_status' => 'completed',
            ],
        ]);
        Http::fake(function ($request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $this->assertSame('150', (string) ($query['source_id'] ?? ''));

            return Http::response([
                'success' => true,
                'data' => [
                    'source_id' => 150,
                    'asset_id' => '019fa943-a59e-71b7-a57a-5cda96124fe1',
                    'nbx_job_id' => 'historical-job',
                    'status' => 'completed',
                    'storage_target' => 'contabo',
                    'faststart_mp4_url' => 'https://objects.example/final/movie_play.mp4',
                ],
                'error' => null,
            ]);
        });

        $synced = app(NbxVideoSourceService::class)->sync($source);

        $this->assertSame($source->id, $synced->id);
        $this->assertSame('https://objects.example/final/movie_play.mp4', $synced->url);
        $this->assertSame(150, $synced->metadata['cdn_source_id']);
        $this->assertSame('synced', $synced->metadata['nbx_sync_status']);
        Http::assertSentCount(1);
    }

    public function test_reconcile_repairs_the_same_tele_ob_record_without_changing_its_type(): void
    {
        config()->set('services.nbx_engine.enabled', true);
        config()->set('services.nbx_engine.base_url', 'https://nbx.example');
        $movie = Movie::factory()->create();
        $source = $movie->videoSources()->create([
            'type' => 'tele_ob',
            'url' => null,
            'file_path' => null,
            'quality' => '480p',
            'format' => 'mp4',
            'is_primary' => false,
            'is_active' => false,
            'processing_job_id' => 'tele-ob-reconcile-job',
            'metadata' => [
                'provider' => 'nbx_engine',
                'nbx_job_id' => 'tele-ob-reconcile-job',
                'telegram_url' => 'https://t.me/naraboxtvcom/100',
                'desired_is_active' => true,
            ],
        ]);
        Http::fake(function ($request) {
            $this->assertStringEndsWith('/api/v1/nbx/jobs/tele-ob-reconcile-job/actions', $request->url());
            $this->assertSame('reconcile', $request['operation']);

            return Http::response([
                'success' => true,
                'data' => [
                    'source_id' => 103,
                    'asset_id' => '019f950e-6c89-730b-aaeb-3f6822750967',
                    'nbx_job_id' => 'tele-ob-reconcile-job',
                    'status' => 'partially_completed',
                    'processing_complete' => true,
                    'storage_verified' => true,
                    'publication_status' => 'complete',
                    'storage_target' => 'contabo',
                    'faststart_mp4_url' => 'https://objects.example/videos/nbx/tele-ob-reconcile-job/faststart/movie.mp4',
                    'metadata' => ['nbx' => ['storage_target' => 'contabo', 'processing_complete' => true]],
                ],
                'error' => null,
            ]);
        });

        $reconciled = app(NbxVideoSourceService::class)->runExplicitAction($source, 'reconcile');

        $this->assertSame($source->id, $reconciled->id);
        $this->assertSame('tele_ob', $reconciled->type);
        $this->assertTrue($reconciled->is_active);
        $this->assertSame('complete', $reconciled->metadata['publication_status']);
        $this->assertStringNotContainsString('t.me/', (string) $reconciled->url);
        Http::assertSentCount(1);
    }

    public function test_direct_storage_registration_derives_url_only_after_object_exists(): void
    {
        config()->set('services.nbx_engine.enabled', true);
        config()->set('services.nbx_engine.base_url', 'https://nbx.example');
        config()->set('services.contabo_object_storage.disk', 'contabo');
        config()->set('services.contabo_object_storage.public_url', 'https://objects.example/nbx');
        Storage::fake('contabo');
        $key = 'videos/episodes/658/episode.mp4';
        Storage::disk('contabo')->put($key, 'verified-object');
        $movie = Movie::factory()->create();
        $source = $movie->videoSources()->create([
            'type' => 'contabo_object_storage',
            'url' => null,
            'file_path' => null,
            'quality' => '480p',
            'format' => 'mp4',
            'media_role' => 'faststart_mp4',
            'storage_disk' => 'contabo',
            'storage_bucket' => 'nbx',
            'storage_object_key' => $key,
            'is_primary' => false,
            'is_active' => true,
        ]);
        Http::fake(function ($request) use ($key) {
            $this->assertStringEndsWith('/api/v1/storage/references', $request->url());
            $this->assertSame($key, $request['object_key']);
            $this->assertSame('https://objects.example/nbx/videos/episodes/658/episode.mp4', $request['object_url']);

            return Http::response([
                'success' => true,
                'data' => ['id' => 77, 'media_asset_id' => '019f950e-6c89-730b-aaeb-3f6822750967'],
                'error' => null,
            ]);
        });

        app(NbxVideoSourceService::class)->registerDirectStorageSource($source);
        $source->refresh();

        $this->assertSame('contabo_object_storage', $source->type);
        $this->assertSame('https://objects.example/nbx/videos/episodes/658/episode.mp4', $source->url);
        $this->assertNotNull($source->verified_at);
        $this->assertCount(1, Storage::disk('contabo')->allFiles());
    }
}
