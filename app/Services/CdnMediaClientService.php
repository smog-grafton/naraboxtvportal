<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CdnMediaClientService
{
    public function importFromUrl(
        string $sourceUrl,
        string $title,
        string $assetType = 'movie',
        string $visibility = 'public',
        ?string $description = null,
        ?string $importMode = null,
        ?string $importStrategy = null
    ): array {
        $payload = [
            'source_url' => $sourceUrl,
            'title' => $title,
            'asset_type' => $assetType,
            'visibility' => $visibility,
            'description' => $description,
            'import_mode' => $this->resolveImportMode($importMode),
            'import_strategy' => $this->resolveImportStrategy($importStrategy),
        ];

        /** @var Response $response */
        $response = $this->client()->post('/api/v1/media/import', $payload);

        return $this->normalizeResponse($response);
    }

    public function uploadFromUploadedFile(
        UploadedFile $file,
        string $title,
        string $assetType = 'movie',
        string $visibility = 'public',
        ?string $description = null
    ): array {
        $payload = [
            'title' => $title,
            'asset_type' => $assetType,
            'visibility' => $visibility,
            'description' => $description,
        ];

        /** @var Response $response */
        $response = $this->client()
            ->attach('file', fopen($file->getRealPath(), 'rb'), $file->getClientOriginalName())
            ->post('/api/v1/media/upload', $payload);

        return $this->normalizeResponse($response);
    }

    public function uploadFromStoragePath(
        string $disk,
        string $path,
        string $title,
        string $assetType = 'movie',
        string $visibility = 'public',
        ?string $description = null
    ): array {
        if (! Storage::disk($disk)->exists($path)) {
            return [
                'ok' => false,
                'status_code' => 422,
                'error' => 'Selected local upload file does not exist in storage.',
                'data' => null,
                'body' => null,
            ];
        }

        $absolutePath = Storage::disk($disk)->path($path);
        $filename = basename($path);
        $payload = [
            'title' => $title,
            'asset_type' => $assetType,
            'visibility' => $visibility,
            'description' => $description,
        ];

        /** @var Response $response */
        $response = $this->client()
            ->attach('file', fopen($absolutePath, 'rb'), $filename)
            ->post('/api/v1/media/upload', $payload);

        return $this->normalizeResponse($response);
    }

    public function getAsset(string $assetId): array
    {
        /** @var Response $response */
        $response = $this->client()->get('/api/v1/media/' . $assetId);

        return $this->normalizeResponse($response);
    }

    public function getSource(int $sourceId): array
    {
        /** @var Response $response */
        $response = $this->client()->get('/api/v1/media/sources/' . $sourceId);

        return $this->normalizeResponse($response);
    }

    public function lookupSourceByUrl(string $sourceUrl): array
    {
        /** @var Response $response */
        $response = $this->client()->get('/api/v1/media/sources/lookup', [
            'source_url' => $sourceUrl,
        ]);

        return $this->normalizeResponse($response);
    }

    public function getPlaybackManifest(string $assetId): array
    {
        if ($this->shouldSkipPlaybackManifestLookup()) {
            Log::warning('Skipping CDN playback manifest lookup because CDN base URL points to this portal instance.', [
                'asset_id' => $assetId,
                'cdn_base_url' => config('services.cdn.base_url'),
                'app_url' => config('app.url'),
            ]);

            return [
                'ok' => false,
                'status_code' => 503,
                'error' => 'CDN playback manifest lookup skipped because the configured CDN base URL points to this portal instance.',
                'data' => null,
                'body' => null,
            ];
        }

        try {
            /** @var Response $response */
            $response = $this->client(3, 8, 0, 0)->get('/api/v1/media/' . $assetId . '/playback');
        } catch (\Throwable $exception) {
            return [
                'ok' => false,
                'status_code' => 503,
                'error' => $exception->getMessage(),
                'data' => null,
                'body' => null,
            ];
        }

        return $this->normalizeResponse($response);
    }

    public function queueSourceOptimization(int $sourceId, ?bool $compressEnabled = null): array
    {
        $payload = [];
        if ($compressEnabled !== null) {
            $payload['compress_enabled'] = $compressEnabled;
        }

        try {
            /** @var Response $response */
            $response = $this->client(3, 15, 0, 0)
                ->post('/api/v1/media/sources/' . $sourceId . '/optimize', $payload);
        } catch (\Throwable $exception) {
            return [
                'ok' => false,
                'status_code' => 503,
                'error' => $exception->getMessage(),
                'data' => null,
                'body' => null,
            ];
        }

        return $this->normalizeResponse($response);
    }

    public function ingestProxyUpload(
        int $sourceId,
        string $assetId,
        string $filename,
        ?string $mimeType,
        ?int $sizeBytes,
        string $absolutePath
    ): array {
        $secret = (string) config('services.cdn.ingest_secret', '');
        if ($secret === '') {
            return [
                'ok' => false,
                'status_code' => 500,
                'error' => 'CDN ingest secret is not configured on portal.',
                'data' => null,
                'body' => null,
            ];
        }

        $timestamp = (string) time();
        $nonce = bin2hex(random_bytes(16));
        $canonical = implode('|', [
            $timestamp,
            $nonce,
            (string) $sourceId,
            $assetId,
            $filename,
            (string) ($sizeBytes ?? ''),
            (string) ($mimeType ?? ''),
        ]);
        $signature = hash_hmac('sha256', $canonical, $secret);
        $endpoint = (string) config('services.cdn.ingest_endpoint', '/api/ingest/asset-source-upload');

        $fileHandle = fopen($absolutePath, 'rb');
        if (! is_resource($fileHandle)) {
            return [
                'ok' => false,
                'status_code' => 500,
                'error' => 'Failed to open downloaded file for CDN ingest upload.',
                'data' => null,
                'body' => null,
            ];
        }

        try {
            /** @var Response $response */
            $response = $this->client()
                ->withHeaders([
                    'X-Ingest-Timestamp' => $timestamp,
                    'X-Ingest-Nonce' => $nonce,
                    'X-Ingest-Signature' => $signature,
                ])
                ->attach('file', $fileHandle, $filename)
                ->post($endpoint, [
                    'source_id' => $sourceId,
                    'asset_id' => $assetId,
                    'filename' => $filename,
                    'mime_type' => $mimeType,
                    'size_bytes' => $sizeBytes,
                ]);
        } finally {
            fclose($fileHandle);
        }

        return $this->normalizeResponse($response);
    }

    private function resolveImportMode(?string $importMode): string
    {
        $mode = $importMode ?: (string) config('services.cdn.default_import_mode', 'now');

        return in_array($mode, ['now', 'queue'], true) ? $mode : 'now';
    }

    private function resolveImportStrategy(?string $importStrategy): string
    {
        $strategy = strtolower(trim((string) ($importStrategy ?: 'auto')));

        return in_array($strategy, ['auto', 'python_worker'], true) ? $strategy : 'auto';
    }

    private function normalizeResponse(Response $response): array
    {
        $body = $response->json();
        $body = is_array($body) ? $body : ['raw' => $response->body()];

        $data = $body['data'] ?? null;
        $error = $body['error'] ?? null;

        if (is_array($data) && empty($error)) {
            $sourceFailure = $data['failure_reason'] ?? null;
            if (is_string($sourceFailure) && $sourceFailure !== '') {
                $error = $sourceFailure;
            }
        }

        return [
            'ok' => $response->successful() && ! $error,
            'status_code' => $response->status(),
            'error' => is_string($error) ? $error : null,
            'data' => is_array($data) ? $data : null,
            'body' => $body,
        ];
    }

    private function client(
        ?int $connectTimeout = null,
        ?int $timeout = null,
        ?int $retryTimes = null,
        ?int $retrySleepMs = null
    ): PendingRequest
    {
        $baseUrl = rtrim((string) config('services.cdn.base_url', ''), '/');
        $token = (string) config('services.cdn.api_token', '');
        $retryTimes = max(0, $retryTimes ?? (int) config('services.cdn.retry_times', 2));
        $retrySleepMs = max(0, $retrySleepMs ?? (int) config('services.cdn.retry_sleep_ms', 800));
        $forceIpResolve = strtolower((string) config('services.cdn.force_ip_resolve', 'v4'));

        $request = Http::baseUrl($baseUrl)
            ->acceptJson()
            ->connectTimeout(max(1, $connectTimeout ?? (int) config('services.cdn.connect_timeout', 15)))
            ->timeout(max(1, $timeout ?? (int) config('services.cdn.timeout', 300)));

        if (in_array($forceIpResolve, ['v4', 'v6'], true)) {
            $request = $request->withOptions([
                'force_ip_resolve' => $forceIpResolve,
            ]);
        }

        if ($token !== '') {
            $request = $request->withToken($token);
        }

        if ($retryTimes > 0) {
            $request = $request->retry($retryTimes, $retrySleepMs);
        }

        return $request;
    }

    private function shouldSkipPlaybackManifestLookup(): bool
    {
        $cdnOrigin = $this->normalizeOrigin((string) config('services.cdn.base_url', ''));
        if ($cdnOrigin === '') {
            return true;
        }

        $appOrigin = $this->normalizeOrigin((string) config('app.url', ''));
        if ($appOrigin !== '' && $cdnOrigin === $appOrigin) {
            return true;
        }

        if (app()->runningInConsole()) {
            return false;
        }

        try {
            $requestOrigin = $this->normalizeOrigin(request()->getSchemeAndHttpHost());
        } catch (\Throwable) {
            $requestOrigin = '';
        }

        return $requestOrigin !== '' && $cdnOrigin === $requestOrigin;
    }

    private function normalizeOrigin(string $url): string
    {
        $trimmed = trim($url);
        if ($trimmed === '') {
            return '';
        }

        if (! str_contains($trimmed, '://')) {
            $trimmed = 'http://' . $trimmed;
        }

        $parts = parse_url($trimmed);
        if (! is_array($parts) || empty($parts['host'])) {
            return '';
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? 'http'));
        $host = strtolower((string) $parts['host']);
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';

        return $scheme . '://' . $host . $port;
    }
}
