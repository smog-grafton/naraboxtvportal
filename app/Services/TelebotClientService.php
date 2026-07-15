<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class TelebotClientService
{
    public function isConfigured(): bool
    {
        return filled($this->baseUrl()) && filled($this->apiToken());
    }

    public function configurationError(): string
    {
        if (! filled($this->baseUrl())) {
            return 'Telebot API base URL is missing. Set TELEBOT_API_BASE_URL and clear config cache.';
        }

        if (! filled($this->apiToken())) {
            return 'Telebot worker token is missing. Set TELEBOT_WORKER_API_TOKEN to the same value as telebot WORKER_API_TOKEN.';
        }

        return 'Telebot worker is not configured correctly.';
    }

    /**
     * @return array{ok: bool, data?: array|null, error?: string|null, status_code?: int|null}
     */
    public function capacity(): array
    {
        return $this->request('get', '/api/worker/capacity');
    }

    /**
     * @return array{ok: bool, job_id?: string|null, data?: array|null, error?: string|null, status_code?: int|null}
     */
    public function createDownloadJob(string $telegramUrl, array $metadata = []): array
    {
        $result = $this->request('post', '/api/worker/jobs', [
            'link' => $telegramUrl,
            'download_only' => true,
            'metadata' => $metadata,
        ]);

        if (! ($result['ok'] ?? false)) {
            return $result + ['job_id' => null];
        }

        $data = is_array($result['data'] ?? null) ? $result['data'] : [];
        $jobId = (string) ($data['job_id'] ?? '');

        if ($jobId === '' && is_array($data['jobs'] ?? null)) {
            $first = $data['jobs'][0] ?? null;
            $jobId = is_array($first) ? (string) ($first['job_id'] ?? '') : '';
        }

        if ($jobId === '') {
            return [
                'ok' => false,
                'job_id' => null,
                'data' => $data,
                'error' => 'Telebot accepted the request but did not return a job ID.',
                'status_code' => $result['status_code'] ?? null,
            ];
        }

        return $result + ['job_id' => $jobId];
    }

    /**
     * @return array{ok: bool, data?: array|null, error?: string|null, status_code?: int|null}
     */
    public function jobStatus(string $jobId): array
    {
        return $this->request('get', '/api/worker/jobs/' . rawurlencode($jobId));
    }

    /**
     * @return array{ok: bool, data?: array|null, error?: string|null, status_code?: int|null}
     */
    public function destroyJob(string $jobId): array
    {
        return $this->request('post', '/api/worker/jobs/' . rawurlencode($jobId) . '/destroy');
    }

    public function absoluteUrl(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return $this->baseUrl() . '/' . ltrim($url, '/');
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.telebot.base_url', ''), '/');
    }

    private function apiToken(): string
    {
        return (string) config('services.telebot.api_token', '');
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())
            ->acceptJson()
            ->withToken($this->apiToken())
            ->connectTimeout((int) config('services.telebot.connect_timeout', 10))
            ->timeout((int) config('services.telebot.timeout', 30));
    }

    /**
     * @return array{ok: bool, data?: array|null, error?: string|null, status_code?: int|null}
     */
    private function request(string $method, string $path, array $payload = []): array
    {
        if (! $this->isConfigured()) {
            return [
                'ok' => false,
                'data' => null,
                'error' => $this->configurationError(),
                'status_code' => null,
            ];
        }

        try {
            /** @var Response $response */
            $response = $method === 'get'
                ? $this->http()->get($path)
                : $this->http()->post($path, $payload);
        } catch (\Throwable $exception) {
            return [
                'ok' => false,
                'data' => null,
                'error' => $exception->getMessage(),
                'status_code' => null,
            ];
        }

        $data = $response->json();
        $data = is_array($data) ? $data : null;

        if (! $response->successful()) {
            return [
                'ok' => false,
                'data' => $data,
                'error' => $this->extractError($response, $data),
                'status_code' => $response->status(),
            ];
        }

        return [
            'ok' => true,
            'data' => $data,
            'error' => null,
            'status_code' => $response->status(),
        ];
    }

    private function extractError(Response $response, ?array $data): string
    {
        foreach ([
            $data['detail'] ?? null,
            $data['message'] ?? null,
            $data['error'] ?? null,
        ] as $message) {
            if (is_string($message) && trim($message) !== '') {
                return trim($message);
            }
        }

        return 'Telebot request failed with HTTP ' . $response->status() . '.';
    }
}
