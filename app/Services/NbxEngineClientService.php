<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class NbxEngineClientService
{
    public function createRemoteJob(array $payload): array
    {
        $payload['input_type'] = 'remote_fetch';

        return $this->normalizeResponse($this->client()->post('/api/v1/nbx/jobs', $payload));
    }

    public function createObjectStorageJob(array $payload): array
    {
        $payload['input_type'] = 'object_storage';

        return $this->normalizeResponse($this->client()->post('/api/v1/nbx/jobs', $payload));
    }

    public function uploadFromStoragePath(string $disk, string $path, array $payload): array
    {
        if (! Storage::disk($disk)->exists($path)) {
            return [
                'ok' => false,
                'status_code' => 422,
                'error' => 'Selected upload file does not exist in portal storage.',
                'data' => null,
                'body' => null,
            ];
        }

        $absolutePath = Storage::disk($disk)->path($path);
        $fileHandle = fopen($absolutePath, 'rb');
        if (! is_resource($fileHandle)) {
            return [
                'ok' => false,
                'status_code' => 500,
                'error' => 'Could not open selected upload file for NBX Engine.',
                'data' => null,
                'body' => null,
            ];
        }

        try {
            $response = $this->client()
                ->attach('file', $fileHandle, basename($path))
                ->post('/api/v1/nbx/jobs/upload', $payload);
        } finally {
            fclose($fileHandle);
        }

        return $this->normalizeResponse($response);
    }

    public function initUploadSession(array $payload): array
    {
        return $this->normalizeResponse($this->client(10, 60)->post('/api/v1/nbx/uploads/init', $payload));
    }

    public function getJob(string $jobId): array
    {
        return $this->normalizeResponse($this->client(5, 30)->get('/api/v1/nbx/jobs/' . rawurlencode($jobId)));
    }

    public function discover(array $query): array
    {
        return $this->normalizeResponse($this->client(5, 30)->get('/api/v1/nbx/discover', $query));
    }

    private function normalizeResponse(Response $response): array
    {
        $body = $response->json();
        $body = is_array($body) ? $body : ['raw' => $response->body()];
        $data = is_array($body['data'] ?? null) ? $body['data'] : null;
        $error = $body['error'] ?? null;

        return [
            'ok' => $response->successful() && ! $error,
            'status_code' => $response->status(),
            'error' => is_string($error) ? $error : null,
            'data' => $data,
            'body' => $body,
        ];
    }

    private function client(?int $connectTimeout = null, ?int $timeout = null): PendingRequest
    {
        $baseUrl = rtrim((string) config('services.nbx_engine.base_url', ''), '/');
        $token = (string) config('services.nbx_engine.api_key', '');

        $request = Http::baseUrl($baseUrl)
            ->acceptJson()
            ->connectTimeout(max(1, $connectTimeout ?? (int) config('services.nbx_engine.connect_timeout', 15)))
            ->timeout(max(1, $timeout ?? (int) config('services.nbx_engine.timeout', 300)));

        if ($token !== '') {
            $request = $request->withToken($token);
        }

        $retryTimes = max(0, (int) config('services.nbx_engine.retry_times', 1));
        if ($retryTimes > 0) {
            $request = $request->retry($retryTimes, max(0, (int) config('services.nbx_engine.retry_sleep_ms', 800)));
        }

        return $request;
    }
}
