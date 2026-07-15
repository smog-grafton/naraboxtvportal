<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NbxVideoSourceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NbxWebhookController extends Controller
{
    public function handle(Request $request, NbxVideoSourceService $service): JsonResponse
    {
        $secret = trim((string) config('services.nbx_engine.webhook_secret', ''));
        if ($secret === '') {
            return response()->json(['message' => 'NBX webhook secret is not configured.'], 503);
        }

        $rawBody = $request->getContent();
        $timestamp = (string) $request->header('X-NBX-Timestamp', '');
        $signature = (string) $request->header('X-NBX-Signature', '');
        $event = (string) $request->header('X-NBX-Event', '');
        $jobId = (string) $request->header('X-NBX-Job-Id', '');

        if (! $this->validTimestamp($timestamp)) {
            return response()->json(['message' => 'Stale or missing NBX webhook timestamp.'], 401);
        }

        if (! $this->validSignature($signature, $timestamp, $rawBody, $secret)) {
            return response()->json(['message' => 'Invalid NBX webhook signature.'], 401);
        }

        $payload = json_decode($rawBody, true);
        if (! is_array($payload)) {
            return response()->json(['message' => 'Invalid NBX webhook JSON payload.'], 422);
        }

        $payloadJobId = (string) ($payload['nbx_job_id'] ?? $payload['job_id'] ?? $payload['source']['nbx_job_id'] ?? '');
        $resolvedJobId = $jobId !== '' ? $jobId : $payloadJobId;
        if ($resolvedJobId === '' || ($payloadJobId !== '' && $jobId !== '' && $payloadJobId !== $jobId)) {
            return response()->json(['message' => 'Invalid NBX webhook job id.'], 422);
        }

        try {
            $source = $service->handleWebhookPayload($payload, $event ?: ($payload['event'] ?? null), $resolvedJobId);
        } catch (\Throwable $throwable) {
            Log::warning('NBX webhook handling failed', [
                'event' => $event ?: ($payload['event'] ?? null),
                'job_id' => $resolvedJobId,
                'error' => $throwable->getMessage(),
            ]);

            return response()->json(['message' => $throwable->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'video_source_id' => $source->id,
            'event' => $event ?: ($payload['event'] ?? null),
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
        if ($signature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret);
        $provided = str_starts_with($signature, 'sha256=')
            ? substr($signature, 7)
            : $signature;

        return is_string($provided) && hash_equals($expected, $provided);
    }
}
