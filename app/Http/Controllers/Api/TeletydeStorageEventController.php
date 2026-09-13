<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TeletydeStorageEvent;
use App\Models\VideoSource;
use App\Services\TelescopeImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TeletydeStorageEventController extends Controller
{
    public function handle(Request $request, TelescopeImportService $telescope): JsonResponse
    {
        $secret = (string) config('services.telebot.callback_secret', '');
        $provided = (string) $request->header('X-Teletyde-Signature', '');
        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);
        if ($secret === '' || $provided === '' || ! hash_equals($expected, $provided)) {
            return response()->json(['accepted' => false, 'error' => 'Invalid Teletyde signature.'], 401);
        }

        $validated = $request->validate([
            'event_id' => ['required', 'uuid'],
            'event_type' => ['required', 'string', 'in:accepted,started,progress,ready,failed,cancelled'],
            'job_id' => ['required', 'string', 'max:64'],
            'portal_source_id' => ['required', 'integer', 'min:1'],
            'status' => ['required', Rule::in(['submitting', 'queued', 'waiting_for_slot', 'waiting_for_space', 'resolving', 'transferring', 'downloading', 'probing', 'inspecting', 'processing', 'preparing_mp4', 'converting_audio', 'optimizing_video', 'validating', 'uploading', 'verifying', 'callback_pending', 'ready', 'failed', 'cancelled'])],
            'progress' => ['required', 'integer', 'min:0', 'max:100'],
            'attempt' => ['sometimes', 'integer', 'min:0'],
            'stage' => ['nullable', 'string', 'max:64'],
            'stage_progress' => ['nullable', 'numeric', 'between:0,100'],
            'download_progress' => ['nullable', 'numeric', 'between:0,100'],
            'processing_progress' => ['nullable', 'numeric', 'between:0,100'],
            'upload_progress' => ['nullable', 'numeric', 'between:0,100'],
            'elapsed_seconds' => ['nullable', 'numeric', 'min:0'],
            'eta_seconds' => ['nullable', 'numeric', 'min:0'],
            'processing' => ['nullable', 'array'],
            'processing_result' => ['nullable', 'array'],
            'processing_result.method' => ['nullable', Rule::in(['passthrough', 'faststart', 'remux', 'audio_transcode', 'video_transcode', 'full_transcode'])],
            'processing_result.source_size' => ['nullable', 'integer', 'min:1'],
            'processing_result.output_size' => ['nullable', 'integer', 'min:1'],
            'processing_result.output_probe' => ['nullable', 'array'],
            'processing_result.validation' => ['nullable', 'array'],
            'metrics' => ['nullable', 'array'],
            'metrics.method' => ['nullable', Rule::in(['passthrough', 'faststart', 'remux', 'audio_transcode', 'video_transcode', 'full_transcode'])],
            'metrics.profile' => ['nullable', Rule::in(array_keys(\App\Services\Media\TelegramProcessingProfile::MODES))],
            'metrics.source_size' => ['nullable', 'integer', 'min:1'],
            'metrics.output_size' => ['nullable', 'integer', 'min:1'],
            'metrics.duration_seconds' => ['nullable', 'numeric', 'min:0'],
            'metrics.source_probe' => ['nullable', 'array'],
            'metrics.output_probe' => ['nullable', 'array'],
            'metrics.validation' => ['nullable', 'array'],
            'output_metadata' => ['nullable', 'array'],
            'bytes_total' => ['nullable', 'integer', 'min:0'],
            'bytes_transferred' => ['nullable', 'integer', 'min:0'],
            'storage' => ['nullable', 'array'],
            'storage.storage_target' => ['nullable', 'string', 'max:64'],
            'storage.provider' => ['nullable', 'string', 'max:64'],
            'storage.bucket' => ['nullable', 'string', 'max:255'],
            'storage.object_key' => ['nullable', 'string', 'max:2048'],
            'storage.public_url' => ['nullable', 'url', 'max:4096'],
            'storage.bytes' => ['nullable', 'integer', 'min:1'],
            'storage.mime_type' => ['nullable', 'string', 'max:255'],
            'storage.etag' => ['nullable', 'string', 'max:255'],
            'telegram' => ['nullable', 'array'],
            'file_name' => ['nullable', 'string', 'max:255'],
            'mime_type' => ['nullable', 'string', 'max:255'],
            'error' => ['nullable', 'string', 'max:4000'],
            'occurred_at' => ['required', 'date'],
        ]);

        $processed = DB::transaction(function () use ($validated, $telescope): bool {
            $source = VideoSource::query()->lockForUpdate()->findOrFail((int) $validated['portal_source_id']);
            abort_unless($source->type === 'telescope', 409, 'Target video source is not a Telescope source.');

            $knownJobId = (string) ($source->processing_job_id ?: data_get($source->metadata, 'telescope_job_id'));
            abort_if($knownJobId !== '' && $knownJobId !== (string) $validated['job_id'], 409, 'Telescope job ID mismatch.');

            $event = TeletydeStorageEvent::firstOrCreate(
                ['event_id' => $validated['event_id']],
                [
                    'job_id' => $validated['job_id'],
                    'video_source_id' => $source->id,
                    'event_type' => $validated['event_type'],
                    'status' => $validated['status'],
                    'payload' => $validated,
                    'processed_at' => now(),
                ]
            );

            if (! $event->wasRecentlyCreated) {
                return false;
            }

            $telescope->applyEvent($source, $validated);

            return true;
        });

        return response()->json(['accepted' => true, 'duplicate' => ! $processed]);
    }
}
