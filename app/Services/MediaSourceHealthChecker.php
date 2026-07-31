<?php

namespace App\Services;

use App\Models\VideoSource;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class MediaSourceHealthChecker
{
    /**
     * @return array{status:string,http_status:?int,error:?string,verified:bool}
     */
    public function check(VideoSource $source, bool $persist = true): array
    {
        $url = app(MediaSourceSelectionService::class)->sourceUrl($source);
        if (! $url) {
            return $this->finish($source, 'invalid', null, 'The source has no usable URL.', false, $persist, null);
        }

        try {
            $result = $this->isHls($source, $url)
                ? $this->checkHls($url)
                : $this->checkProgressive($url);
        } catch (\Throwable) {
            $result = [
                'status' => 'unreachable',
                'http_status' => null,
                'error' => 'The media host could not be reached within the health-check timeout.',
                'verified' => false,
            ];
        }

        return $this->finish(
            $source,
            $result['status'],
            $result['http_status'],
            $result['error'],
            $result['verified'],
            $persist,
            $url,
        );
    }

    public function isFresh(VideoSource $source): bool
    {
        if (! $source->last_health_check_at) {
            return false;
        }

        $minutes = max(1, (int) config('video_sources.health.ttl_minutes', 30));

        return $source->last_health_check_at->gte(now()->subMinutes($minutes));
    }

    /**
     * @return array{status:string,http_status:?int,error:?string,verified:bool}
     */
    private function checkHls(string $url): array
    {
        $master = $this->client()
            ->accept('application/vnd.apple.mpegurl, application/x-mpegURL, */*')
            ->get($url);

        if (! $master->successful()) {
            return $this->httpFailure($master->status(), 'HLS master playlist is unreachable.');
        }

        $body = $this->boundedBody($master);
        if (! str_contains($body, '#EXTM3U') || str_contains(strtolower($body), '<html')) {
            return [
                'status' => 'invalid',
                'http_status' => $master->status(),
                'error' => 'The HLS URL returned content that is not a valid manifest.',
                'verified' => false,
            ];
        }

        $firstReference = $this->firstManifestReference($body);
        if ($firstReference !== null) {
            $referenceUrl = $this->resolveReference($url, $firstReference);
            $isPlaylist = str_contains(strtolower((string) parse_url($referenceUrl, PHP_URL_PATH)), '.m3u8');
            $reference = $isPlaylist
                ? $this->client()->accept('application/vnd.apple.mpegurl, */*')->get($referenceUrl)
                : $this->client()->withHeaders(['Range' => 'bytes=0-1023'])->get($referenceUrl);

            if (! in_array($reference->status(), [200, 206], true)) {
                return $this->httpFailure($reference->status(), 'An HLS child playlist or media segment is unreachable.');
            }

            if ($isPlaylist && ! str_contains($this->boundedBody($reference), '#EXTM3U')) {
                return [
                    'status' => 'invalid',
                    'http_status' => $reference->status(),
                    'error' => 'The HLS master references an invalid child playlist.',
                    'verified' => false,
                ];
            }

            if ($isPlaylist) {
                $childReference = $this->firstManifestReference($this->boundedBody($reference));
                if ($childReference !== null) {
                    $childUrl = $this->resolveReference($referenceUrl, $childReference);
                    $childIsPlaylist = str_contains(
                        strtolower((string) parse_url($childUrl, PHP_URL_PATH)),
                        '.m3u8'
                    );
                    $child = $childIsPlaylist
                        ? $this->client()->accept('application/vnd.apple.mpegurl, */*')->get($childUrl)
                        : $this->client()->withHeaders(['Range' => 'bytes=0-1023'])->get($childUrl);

                    if (! in_array($child->status(), [200, 206], true)) {
                        return $this->httpFailure(
                            $child->status(),
                            'An HLS media segment referenced by the child playlist is unreachable.'
                        );
                    }

                    if ($childIsPlaylist && ! str_contains($this->boundedBody($child), '#EXTM3U')) {
                        return [
                            'status' => 'invalid',
                            'http_status' => $child->status(),
                            'error' => 'The HLS child playlist references an invalid nested playlist.',
                            'verified' => false,
                        ];
                    }
                }
            }
        }

        return [
            'status' => 'healthy',
            'http_status' => $master->status(),
            'error' => null,
            'verified' => true,
        ];
    }

    /**
     * @return array{status:string,http_status:?int,error:?string,verified:bool}
     */
    private function checkProgressive(string $url): array
    {
        $response = $this->client()->head($url);
        $contentType = strtolower((string) $response->header('Content-Type', ''));
        $contentLength = (int) ($response->header('Content-Length') ?: 0);

        if (! $response->successful()
            || in_array($response->status(), [405, 501], true)
            || str_contains($contentType, 'text/html')
            || ($contentLength === 0 && $response->status() !== 204)
        ) {
            $response = $this->client()
                ->withHeaders(['Range' => 'bytes=0-1023', 'Accept' => 'video/*, application/octet-stream;q=0.9, */*;q=0.1'])
                ->get($url);
            $contentType = strtolower((string) $response->header('Content-Type', ''));
        }

        if (! in_array($response->status(), [200, 206], true)) {
            return $this->httpFailure($response->status(), 'Progressive media is unreachable.');
        }

        if (str_contains($contentType, 'text/html')) {
            return [
                'status' => 'invalid',
                'http_status' => $response->status(),
                'error' => 'The media URL returned an HTML page instead of video data.',
                'verified' => false,
            ];
        }

        $contentRange = (string) $response->header('Content-Range', '');
        $length = (int) ($response->header('Content-Length') ?: 0);
        if ($length <= 0 && $contentRange === '' && $response->body() === '') {
            return [
                'status' => 'degraded',
                'http_status' => $response->status(),
                'error' => 'The media host responded but did not provide evidence of a non-empty file.',
                'verified' => false,
            ];
        }

        return [
            'status' => 'healthy',
            'http_status' => $response->status(),
            'error' => null,
            'verified' => true,
        ];
    }

    private function client(): PendingRequest
    {
        return Http::connectTimeout(max(1, (int) config('video_sources.health.connect_timeout_seconds', 3)))
            ->timeout(max(1, (int) config('video_sources.health.timeout_seconds', 8)))
            ->withOptions(['allow_redirects' => ['max' => 3]])
            ->retry(
                max(1, (int) config('video_sources.health.retry_times', 1) + 1),
                max(0, (int) config('video_sources.health.retry_sleep_ms', 250)),
                throw: false,
            );
    }

    private function isHls(VideoSource $source, string $url): bool
    {
        return in_array(strtolower((string) $source->format), ['hls', 'm3u8'], true)
            || ($source->media_role === 'hls_master')
            || str_ends_with(strtolower((string) parse_url($url, PHP_URL_PATH)), '.m3u8');
    }

    private function firstManifestReference(string $manifest): ?string
    {
        foreach (preg_split('/\R/', $manifest) ?: [] as $line) {
            $line = trim($line);
            if ($line !== '' && ! str_starts_with($line, '#')) {
                return $line;
            }
        }

        return null;
    }

    private function resolveReference(string $manifestUrl, string $reference): string
    {
        if (preg_match('~^https?://~i', $reference) === 1) {
            return $reference;
        }

        $parts = parse_url($manifestUrl);
        $scheme = (string) ($parts['scheme'] ?? 'https');
        $host = (string) ($parts['host'] ?? '');
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        if (str_starts_with($reference, '/')) {
            return "{$scheme}://{$host}{$port}{$reference}";
        }

        $directory = rtrim(str_replace('\\', '/', dirname((string) ($parts['path'] ?? '/'))), '/');

        return "{$scheme}://{$host}{$port}{$directory}/{$reference}";
    }

    private function boundedBody(Response $response): string
    {
        return substr((string) $response->body(), 0, 262144);
    }

    /**
     * @return array{status:string,http_status:?int,error:?string,verified:bool}
     */
    private function httpFailure(int $status, string $message): array
    {
        return [
            'status' => in_array($status, [404, 410], true) ? 'unreachable' : 'degraded',
            'http_status' => $status,
            'error' => $message,
            'verified' => false,
        ];
    }

    /**
     * @return array{status:string,http_status:?int,error:?string,verified:bool}
     */
    private function finish(
        VideoSource $source,
        string $status,
        ?int $httpStatus,
        ?string $error,
        bool $verified,
        bool $persist,
        ?string $checkedUrl,
    ): array {
        if ($persist) {
            $failures = $verified ? 0 : ((int) $source->consecutive_failures + 1);
            VideoSource::withoutEvents(function () use ($source, $status, $httpStatus, $error, $verified, $failures, $checkedUrl): void {
                $source->forceFill([
                    'health_status' => $status,
                    'last_health_check_at' => now(),
                    'last_http_status' => $httpStatus,
                    'last_health_error' => $error,
                    'consecutive_failures' => $failures,
                    'verified_at' => $verified ? now() : $source->verified_at,
                    'metadata' => array_merge((array) $source->metadata, [
                        'health_checked_url' => $checkedUrl,
                    ]),
                ])->save();
            });
        }

        return [
            'status' => $status,
            'http_status' => $httpStatus,
            'error' => $error,
            'verified' => $verified,
        ];
    }
}
