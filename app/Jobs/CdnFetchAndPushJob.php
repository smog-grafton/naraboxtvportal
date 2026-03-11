<?php

namespace App\Jobs;

use App\Services\CdnMediaClientService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\Response;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CdnFetchAndPushJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public int $timeout = 7200;

    public function __construct(
        public int $sourceId,
        public string $assetId,
        public string $url,
        public ?string $filename = null,
        public ?string $mimeType = null,
        public ?int $sizeBytes = null
    ) {
    }

    public function handle(CdnMediaClientService $cdnMediaClientService): void
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'cdn_proxy_');
        if (! is_string($tempPath) || $tempPath === '') {
            throw new \RuntimeException('Failed to allocate temporary file for proxy download.');
        }

        try {
            /** @var Response $response */
            $response = Http::connectTimeout(60)
                ->timeout(7200)
                ->retry(2, 1500)
                ->withHeaders([
                    'User-Agent' => 'NaraboxPortalProxy/1.0',
                    'Accept' => '*/*',
                ])
                ->withOptions([
                    'sink' => $tempPath,
                ])
                ->get($this->url);

            if (! $response->successful()) {
                throw new \RuntimeException('Portal proxy failed to download remote source: HTTP ' . $response->status());
            }

            if (! is_file($tempPath) || filesize($tempPath) <= 0) {
                throw new \RuntimeException('Portal proxy download produced an empty file.');
            }

            $finalFilename = $this->resolveFilename($this->filename, $this->url);
            $size = (int) filesize($tempPath);
            $mimeType = $this->mimeType ?: (@mime_content_type($tempPath) ?: 'application/octet-stream');

            $uploadResult = $cdnMediaClientService->ingestProxyUpload(
                $this->sourceId,
                $this->assetId,
                $finalFilename,
                $mimeType,
                $size > 0 ? $size : $this->sizeBytes,
                $tempPath
            );

            if (! ($uploadResult['ok'] ?? false)) {
                throw new \RuntimeException((string) ($uploadResult['error'] ?? 'CDN ingest upload failed from portal proxy.'));
            }
        } catch (\Throwable $throwable) {
            Log::error('Portal proxy fetch-and-push failed', [
                'source_id' => $this->sourceId,
                'asset_id' => $this->assetId,
                'url' => $this->url,
                'error' => $throwable->getMessage(),
            ]);

            throw $throwable;
        } finally {
            if (is_file($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    private function resolveFilename(?string $filename, string $url): string
    {
        $candidate = $this->sanitizeFilename($filename);
        if ($candidate !== null) {
            return $candidate;
        }

        $pathCandidate = $this->sanitizeFilename(basename((string) parse_url($url, PHP_URL_PATH)));
        if ($pathCandidate !== null) {
            return $pathCandidate;
        }

        $query = (string) parse_url($url, PHP_URL_QUERY);
        if ($query !== '') {
            parse_str($query, $params);
            foreach (['file', 'filename', 'name', 'title', 'download', 'url', 'path'] as $key) {
                $queryCandidate = $this->sanitizeFilename(basename((string) ($params[$key] ?? '')));
                if ($queryCandidate !== null) {
                    return $queryCandidate;
                }
            }
        }

        return sprintf('source-%d.mp4', $this->sourceId);
    }

    private function sanitizeFilename(?string $filename): ?string
    {
        if (! is_string($filename) || trim($filename) === '') {
            return null;
        }

        $decoded = urldecode($filename);
        $clean = preg_replace('/[^A-Za-z0-9._-]/', '_', $decoded) ?: '';
        $clean = ltrim($clean, '.');

        return $clean !== '' ? $clean : null;
    }
}

