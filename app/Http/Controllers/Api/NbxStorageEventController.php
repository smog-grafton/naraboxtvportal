<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VideoSource;
use App\Services\MediaSourceSelectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NbxStorageEventController extends Controller
{
    public function handle(Request $request, MediaSourceSelectionService $selection): JsonResponse
    {
        $secret = trim((string) config('services.nbx_engine.webhook_secret', ''));
        if ($secret === '') {
            return response()->json(['message' => 'NBX webhook secret is not configured.'], 503);
        }

        $rawBody = $request->getContent();
        $timestamp = (string) $request->header('X-NBX-Timestamp', '');
        $signature = (string) $request->header('X-NBX-Signature', '');
        if (! $this->validTimestamp($timestamp)
            || ! $this->validSignature($signature, $timestamp, $rawBody, $secret)
        ) {
            return response()->json(['message' => 'Invalid or stale NBX storage signature.'], 401);
        }

        $payload = json_decode($rawBody, true);
        if (! is_array($payload)) {
            return response()->json(['message' => 'Invalid NBX storage event JSON payload.'], 422);
        }

        $validated = validator($payload, [
            'event' => ['required', 'string', 'max:96'],
            'phase' => ['required', 'in:planned,deleted'],
            'portal_source_id' => ['required', 'integer', 'min:1'],
            'portal_sourceable_type' => ['nullable', 'string', 'max:191'],
            'portal_sourceable_id' => ['nullable', 'integer', 'min:1'],
            'media_role' => ['required', 'string', 'max:48'],
            'deleted_object_keys' => ['sometimes', 'array', 'max:5000'],
            'deleted_object_keys.*' => ['string', 'max:2048'],
        ])->validate();

        $source = VideoSource::query()->find($validated['portal_source_id']);
        if (! $source) {
            return response()->json(['message' => 'Portal video source was not found.'], 404);
        }
        if (($validated['portal_sourceable_type'] ?? null)
            && $source->sourceable_type !== $validated['portal_sourceable_type']
        ) {
            return response()->json(['message' => 'Portal source owner type does not match.'], 409);
        }
        if (($validated['portal_sourceable_id'] ?? null)
            && (int) $source->sourceable_id !== (int) $validated['portal_sourceable_id']
        ) {
            return response()->json(['message' => 'Portal source owner id does not match.'], 409);
        }

        try {
            DB::transaction(function () use ($source, $validated): void {
                $fresh = VideoSource::query()->lockForUpdate()->findOrFail($source->id);
                $metadata = (array) ($fresh->metadata ?? []);
                $metadata['nbx_storage_event'] = [
                    'event' => $validated['event'],
                    'phase' => $validated['phase'],
                    'media_role' => $validated['media_role'],
                    'object_keys' => $validated['deleted_object_keys'] ?? [],
                    'received_at' => now()->toIso8601String(),
                ];

                $changes = [
                    'is_active' => false,
                    'is_primary' => false,
                    'health_status' => 'unreachable',
                    'last_health_check_at' => now(),
                    'last_health_error' => $validated['phase'] === 'planned'
                        ? 'NBX storage deletion is in progress.'
                        : 'The backing object was deleted through NBX storage management.',
                    'metadata' => $metadata,
                ];
                if ($validated['phase'] === 'deleted') {
                    $changes['deleted_from_storage_at'] = now();
                }

                VideoSource::withoutEvents(fn () => $fresh->forceFill($changes)->save());
            });

            $fallback = $source->sourceable
                ? $selection->candidatesFor($source->sourceable)
                    ->first(fn (VideoSource $candidate): bool => $candidate->id !== $source->id && $candidate->health_status === 'healthy'
                    )
                : null;
            if ($fallback) {
                $selection->promoteIfHealthy($fallback, 'nbx_storage_deletion');
            }
        } catch (\Throwable $exception) {
            Log::warning('NBX storage event reconciliation failed', [
                'portal_source_id' => $source->id,
                'event' => $validated['event'],
                'phase' => $validated['phase'],
                'error' => $exception->getMessage(),
            ]);

            return response()->json(['message' => 'Storage event reconciliation failed.'], 500);
        }

        return response()->json([
            'ok' => true,
            'video_source_id' => $source->id,
            'fallback_source_id' => $fallback?->id,
            'phase' => $validated['phase'],
        ]);
    }

    private function validTimestamp(string $timestamp): bool
    {
        if ($timestamp === '' || ! ctype_digit($timestamp)) {
            return false;
        }

        $tolerance = max(30, (int) config('services.nbx_engine.webhook_tolerance_seconds', 300));

        return abs(time() - (int) $timestamp) <= $tolerance;
    }

    private function validSignature(string $signature, string $timestamp, string $rawBody, string $secret): bool
    {
        $provided = str_starts_with($signature, 'sha256=') ? substr($signature, 7) : $signature;
        if ($provided === '') {
            return false;
        }

        return hash_equals(
            hash_hmac('sha256', $timestamp.'.'.$rawBody, $secret),
            $provided,
        );
    }
}
