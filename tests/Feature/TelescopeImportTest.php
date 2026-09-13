<?php

namespace Tests\Feature;

use App\Models\Movie;
use App\Models\TeletydeStorageEvent;
use App\Models\VideoSource;
use App\Services\TelebotClientService;
use App\Services\TelescopeImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelescopeImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_portal_submits_telescope_once_without_nbx_or_storage_credentials(): void
    {
        config()->set('services.telebot.base_url', 'https://teletyde.example');
        config()->set('services.telebot.api_token', 'worker-token');
        Http::fake([
            'https://teletyde.example/api/worker/telescope/jobs' => Http::response([
                'accepted' => true,
                'job_id' => 'telescope-job-1',
                'status' => 'queued',
            ], 202),
        ]);
        $movie = Movie::factory()->create();

        $source = app(TelescopeImportService::class)->submit($movie, [
            'url' => 'https://t.me/c/2489865945/10/45517',
            'nbx_storage_target' => 'contabo_nb_nbx',
            'quality' => 'auto',
            'format' => 'mkv',
            'is_active' => true,
        ], 'movie');

        $this->assertSame('telescope', $source->type);
        $this->assertSame('telescope-job-1', $source->processing_job_id);
        $this->assertSame('queued', $source->metadata['telescope_status']);
        $this->assertFalse((bool) $source->is_active);
        $this->assertNull($source->url);
        Http::assertSent(function ($request): bool {
            $payload = $request->data();

            return $request->url() === 'https://teletyde.example/api/worker/telescope/jobs'
                && $payload['storage_target'] === 'contabo_nb_nbx'
                && $payload['portal_source_id'] > 0
                && ! isset($payload['access_key'], $payload['secret_key']);
        });
    }

    public function test_signed_ready_callback_is_idempotent_and_activates_public_object_url(): void
    {
        config()->set('services.telebot.callback_secret', 'callback-secret');
        $movie = Movie::factory()->create();
        $source = $movie->videoSources()->create([
            'type' => 'telescope',
            'quality' => 'auto',
            'format' => 'mkv',
            'is_primary' => true,
            'is_active' => false,
            'processing_job_id' => 'telescope-job-1',
            'metadata' => [
                'provider' => 'telescope',
                'telegram_url' => 'https://t.me/c/2489865945/45515',
                'telescope_job_id' => 'telescope-job-1',
                'telescope_status' => 'queued',
                'requested_active' => true,
            ],
        ]);
        $payload = [
            'event_id' => 'b994e2f7-1a46-4ad7-8d66-5dc10a761fd3',
            'event_type' => 'ready',
            'job_id' => 'telescope-job-1',
            'portal_source_id' => $source->id,
            'status' => 'ready',
            'progress' => 100,
            'bytes_total' => 1405642995,
            'bytes_transferred' => 1405642995,
            'storage' => [
                'storage_target' => 'contabo_nb_nbx',
                'provider' => 'contabo',
                'bucket' => 'nb-nbx',
                'object_key' => 'videos/telescope/2026/09/07/job/movie.mkv',
                'public_url' => 'https://objects.example/videos/telescope/2026/09/07/job/movie.mkv',
                'bytes' => 1405642995,
                'mime_type' => 'video/x-matroska',
                'etag' => 'multipart-etag',
            ],
            'telegram' => ['peer_id' => -1002489865945, 'message_id' => 45515, 'topic_id' => null],
            'file_name' => 'movie.mkv',
            'mime_type' => 'video/x-matroska',
            'error' => null,
            'occurred_at' => now()->toIso8601String(),
        ];
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $signature = 'sha256='.hash_hmac('sha256', $body, 'callback-secret');
        $server = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_TELETYDE_SIGNATURE' => $signature,
            'HTTP_X_TELETYDE_EVENT' => $payload['event_id'],
        ];

        $this->call('POST', '/api/v1/teletyde/storage-events', [], [], [], $server, $body)
            ->assertOk()
            ->assertJson(['accepted' => true]);
        $this->call('POST', '/api/v1/teletyde/storage-events', [], [], [], $server, $body)
            ->assertOk()
            ->assertJson(['accepted' => true, 'duplicate' => true]);

        $source->refresh();
        $this->assertSame('ready', $source->metadata['telescope_status']);
        $this->assertSame('https://objects.example/videos/telescope/2026/09/07/job/movie.mkv', $source->url);
        $this->assertSame('contabo_nb_nbx', $source->storage_target_key);
        $this->assertSame('nb-nbx', $source->storage_bucket);
        $this->assertSame(1405642995, $source->file_size);
        $this->assertTrue((bool) $source->is_active);
        $this->assertSame(1, TeletydeStorageEvent::query()->count());
    }

    public function test_callback_rejects_invalid_signature(): void
    {
        config()->set('services.telebot.callback_secret', 'callback-secret');

        $this->withHeader('X-Teletyde-Signature', 'sha256=wrong')
            ->postJson('/api/v1/teletyde/storage-events', [])
            ->assertUnauthorized();
    }

    public function test_scheduled_polling_recovers_a_missed_ready_callback(): void
    {
        config()->set('services.telebot.base_url', 'https://teletyde.example');
        config()->set('services.telebot.api_token', 'worker-token');
        $movie = Movie::factory()->create();
        $source = $movie->videoSources()->create([
            'type' => 'telescope',
            'quality' => 'auto',
            'format' => 'mkv',
            'is_active' => false,
            'processing_job_id' => 'telescope-job-poll',
            'metadata' => [
                'telescope_status' => 'transferring',
                'requested_active' => true,
            ],
        ]);
        Http::fake([
            'https://teletyde.example/api/worker/telescope/jobs/telescope-job-poll' => Http::response([
                'job_id' => 'telescope-job-poll',
                'status' => 'ready',
                'progress' => 100,
                'bytes_total' => 6000000,
                'bytes_transferred' => 6000000,
                'result' => [
                    'storage_target' => 'r2_nbx',
                    'bucket' => 'nbx',
                    'object_key' => 'videos/telescope/job/movie.mkv',
                    'public_url' => 'https://objects.example/videos/telescope/job/movie.mkv',
                    'bytes' => 6000000,
                ],
            ]),
        ]);

        $this->artisan('telescope:sync-video-sources')->assertSuccessful();

        $source->refresh();
        $this->assertSame('ready', $source->metadata['telescope_status']);
        $this->assertSame('https://objects.example/videos/telescope/job/movie.mkv', $source->url);
        $this->assertTrue((bool) $source->is_active);
    }

    public function test_late_progress_callback_cannot_downgrade_a_ready_source(): void
    {
        $movie = Movie::factory()->create();
        $source = $movie->videoSources()->create([
            'type' => 'telescope',
            'url' => 'https://objects.example/movie.mkv',
            'quality' => 'auto',
            'format' => 'mkv',
            'is_active' => true,
            'processing_job_id' => 'telescope-job-order',
            'metadata' => [
                'telescope_status' => 'ready',
                'telescope_progress' => 100,
                'requested_active' => true,
            ],
        ]);

        $updated = app(TelescopeImportService::class)->applyEvent($source, [
            'job_id' => 'telescope-job-order',
            'event_type' => 'progress',
            'status' => 'transferring',
            'progress' => 40,
        ]);

        $this->assertSame('ready', $updated->metadata['telescope_status']);
        $this->assertSame(100, $updated->metadata['telescope_progress']);
        $this->assertSame('https://objects.example/movie.mkv', $updated->url);
        $this->assertTrue((bool) $updated->is_active);
    }

    public function test_fast_callback_cannot_be_overwritten_by_submission_response(): void
    {
        $telebot = new class extends TelebotClientService
        {
            public function createTelescopeJob(
                string $telegramUrl,
                int $portalSourceId,
                string $storageTarget = 'auto',
                array $metadata = [],
                array $processing = [],
            ): array {
                $source = VideoSource::findOrFail($portalSourceId);
                $source->update([
                    'metadata' => array_merge((array) $source->metadata, [
                        'telescope_status' => 'transferring',
                        'telescope_progress' => 20,
                        'last_message' => 'Transfer already started.',
                    ]),
                ]);

                return ['ok' => true, 'job_id' => 'fast-job', 'status_code' => 202];
            }
        };
        $this->app->instance(TelebotClientService::class, $telebot);
        $movie = Movie::factory()->create();

        $source = app(TelescopeImportService::class)->submit($movie, [
            'url' => 'https://t.me/demo/123',
            'is_active' => true,
        ], 'movie');

        $this->assertSame('fast-job', $source->processing_job_id);
        $this->assertSame('transferring', $source->metadata['telescope_status']);
        $this->assertSame(20, $source->metadata['telescope_progress']);
        $this->assertSame('Transfer already started.', $source->metadata['last_message']);
    }
}
