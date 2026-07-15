<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class BunnyStreamClientService
{
    public function isConfigured(): bool
    {
        return (bool) config('services.bunny_stream.enabled', false)
            && $this->libraryId() !== ''
            && $this->apiKey() !== ''
            && $this->pullZoneHostname() !== '';
    }

    public function createVideo(string $title, ?string $collectionId = null, ?int $thumbnailTime = null): array
    {
        if (! $this->isConfigured()) {
            return $this->configurationError();
        }

        $payload = [
            'title' => $title,
        ];

        $collectionId = $collectionId ?: (string) config('services.bunny_stream.collection_id', '');
        if ($collectionId !== '') {
            $payload['collectionId'] = $collectionId;
        }
        if ($thumbnailTime !== null) {
            $payload['thumbnailTime'] = $thumbnailTime;
        }

        /** @var Response $response */
        $response = $this->client()->post('/library/' . $this->libraryId() . '/videos', $payload);

        return $this->normalizeResponse($response);
    }

    public function uploadFromStoragePath(
        string $disk,
        string $path,
        string $title,
        ?string $collectionId = null
    ): array {
        if (! $this->isConfigured()) {
            return $this->configurationError();
        }

        if (! Storage::disk($disk)->exists($path)) {
            return [
                'ok' => false,
                'status_code' => 422,
                'error' => 'Selected local upload file does not exist in storage.',
                'data' => null,
                'body' => null,
            ];
        }

        $create = $this->createVideo($title, $collectionId);
        if (! ($create['ok'] ?? false)) {
            return $create;
        }

        $createdVideo = (array) ($create['data'] ?? []);
        $videoId = (string) ($createdVideo['guid'] ?? '');
        if ($videoId === '') {
            return [
                'ok' => false,
                'status_code' => 502,
                'error' => 'Bunny Stream created a video record but did not return a video ID.',
                'data' => ['create_response' => $createdVideo],
                'body' => $create['body'] ?? null,
            ];
        }

        $absolutePath = Storage::disk($disk)->path($path);
        $fileHandle = fopen($absolutePath, 'rb');
        if (! is_resource($fileHandle)) {
            return [
                'ok' => false,
                'status_code' => 500,
                'error' => 'Failed to open local upload file for Bunny Stream.',
                'data' => ['video_id' => $videoId],
                'body' => null,
            ];
        }

        try {
            /** @var Response $response */
            $response = $this->client(false)
                ->withHeaders(['Content-Type' => 'application/octet-stream'])
                ->send('PUT', $this->videoPath($videoId, true), [
                    'body' => $fileHandle,
                ]);
        } finally {
            fclose($fileHandle);
        }

        $upload = $this->normalizeResponse($response);
        if (! ($upload['ok'] ?? false)) {
            $upload['data'] = array_merge((array) ($upload['data'] ?? []), [
                'video_id' => $videoId,
                'create_response' => $createdVideo,
            ]);

            return $upload;
        }

        $video = $this->getVideo($videoId);
        $videoData = ($video['ok'] ?? false) ? (array) ($video['data'] ?? []) : $createdVideo;

        return [
            'ok' => true,
            'status_code' => $upload['status_code'] ?? 200,
            'error' => null,
            'data' => [
                'video_id' => $videoId,
                'video' => $videoData,
                'create_response' => $createdVideo,
                'upload_response' => $upload['body'] ?? null,
                'playback' => $this->buildPlaybackPayload($videoId, $videoData),
            ],
            'body' => $upload['body'] ?? null,
        ];
    }

    public function fetchVideoFromUrl(
        string $sourceUrl,
        string $title,
        array $headers = [],
        ?string $collectionId = null
    ): array {
        if (! $this->isConfigured()) {
            return $this->configurationError();
        }

        $payload = [
            'url' => $sourceUrl,
            'title' => $title,
            'headers' => (object) $headers,
        ];

        $query = [];
        $collectionId = $collectionId ?: (string) config('services.bunny_stream.collection_id', '');
        if ($collectionId !== '') {
            $query['collectionId'] = $collectionId;
        }

        $endpoint = '/library/' . $this->libraryId() . '/videos/fetch';
        if ($query !== []) {
            $endpoint .= '?' . http_build_query($query);
        }

        /** @var Response $response */
        $response = $this->client()->post($endpoint, $payload);
        $fetch = $this->normalizeResponse($response);
        if (! ($fetch['ok'] ?? false)) {
            return $fetch;
        }

        $video = $this->findLatestVideoByTitle($title);
        $videoData = ($video['ok'] ?? false) ? (array) ($video['data'] ?? []) : [];
        $videoId = (string) ($videoData['guid'] ?? '');

        return [
            'ok' => true,
            'status_code' => $fetch['status_code'] ?? 200,
            'error' => null,
            'data' => [
                'video_id' => $videoId !== '' ? $videoId : null,
                'video' => $videoData !== [] ? $videoData : null,
                'fetch_response' => $fetch['body'] ?? null,
                'playback' => $videoId !== '' ? $this->buildPlaybackPayload($videoId, $videoData) : null,
            ],
            'body' => $fetch['body'] ?? null,
        ];
    }

    public function getVideo(string $videoId): array
    {
        if (! $this->isConfigured()) {
            return $this->configurationError();
        }

        /** @var Response $response */
        $response = $this->client()->get('/library/' . $this->libraryId() . '/videos/' . $videoId);

        return $this->normalizeResponse($response);
    }

    public function listVideos(array $query = []): array
    {
        if (! $this->isConfigured()) {
            return $this->configurationError();
        }

        /** @var Response $response */
        $response = $this->client()->get('/library/' . $this->libraryId() . '/videos', $query);

        return $this->normalizeResponse($response);
    }

    public function findLatestVideoByTitle(string $title): array
    {
        $videos = $this->listVideos([
            'page' => 1,
            'itemsPerPage' => 10,
            'search' => $title,
            'orderBy' => 'date',
        ]);

        if (! ($videos['ok'] ?? false)) {
            return $videos;
        }

        $items = (array) (($videos['data']['items'] ?? null) ?: []);
        $match = collect($items)->first(fn ($item) => is_array($item) && (string) ($item['title'] ?? '') === $title)
            ?: ($items[0] ?? null);

        if (! is_array($match)) {
            return [
                'ok' => false,
                'status_code' => 404,
                'error' => 'Bunny Stream accepted the fetch, but the created video could not be found yet.',
                'data' => null,
                'body' => $videos['body'] ?? null,
            ];
        }

        return [
            'ok' => true,
            'status_code' => 200,
            'error' => null,
            'data' => $match,
            'body' => $videos['body'] ?? null,
        ];
    }

    public function buildPlaybackPayload(string $videoId, ?array $video = null): array
    {
        $video = $video ?? [];
        $status = array_key_exists('status', $video) ? (int) $video['status'] : null;
        $thumbnailFileName = (string) ($video['thumbnailFileName'] ?? '');
        $hlsUrl = $this->streamAssetUrl($videoId, 'playlist.m3u8');
        $mp4Url = $this->resolveMp4FallbackUrl($videoId, $video);
        $originalUrl = $this->resolveOriginalFileUrl($videoId, $video);
        $downloadUrl = $originalUrl ?: $mp4Url;

        return [
            'provider' => 'bunny_stream',
            'video_id' => $videoId,
            'library_id' => $this->libraryId(),
            'type' => 'hls',
            'url' => $hlsUrl,
            'hls_master_url' => $hlsUrl,
            'mp4_play_url' => $mp4Url,
            'mp4_url' => $mp4Url,
            'original_url' => $originalUrl,
            'download_url' => $downloadUrl,
            'direct_play_url' => 'https://video.bunnycdn.com/play/' . $this->libraryId() . '/' . $videoId,
            'thumbnail_url' => $thumbnailFileName !== '' ? $this->streamAssetUrl($videoId, $thumbnailFileName) : null,
            'preview_url' => $this->streamAssetUrl($videoId, 'preview.webp'),
            'qualities' => [[
                'id' => 'auto',
                'label' => 'AUTO',
                'url' => $hlsUrl,
                'bandwidth' => null,
                'width' => isset($video['width']) ? (int) $video['width'] : null,
                'height' => isset($video['height']) ? (int) $video['height'] : null,
            ]],
            'status' => $status,
            'status_label' => $this->statusLabel($status),
            'encode_progress' => isset($video['encodeProgress']) ? (int) $video['encodeProgress'] : null,
            'is_playable' => $status === null || in_array($status, [3, 4], true),
        ];
    }

    public function isBunnyStreamUrl(string $url): bool
    {
        return $this->extractVideoId($url) !== null;
    }

    public function extractVideoId(string $url): ?string
    {
        $parts = parse_url(trim($url));
        if (! is_array($parts) || empty($parts['host'])) {
            return null;
        }

        $host = strtolower((string) $parts['host']);
        $segments = array_values(array_filter(explode('/', trim((string) ($parts['path'] ?? ''), '/'))));
        if ($segments === []) {
            return null;
        }

        if ($host === 'video.bunnycdn.com' && ($segments[0] ?? null) === 'play' && isset($segments[2])) {
            return $this->looksLikeGuid($segments[2]) ? $segments[2] : null;
        }

        $pullHost = strtolower($this->pullZoneHostname());
        if ($host === $pullHost || str_ends_with($host, '.b-cdn.net')) {
            return $this->looksLikeGuid($segments[0]) ? $segments[0] : null;
        }

        return null;
    }

    public function streamAssetUrl(string $videoId, string $assetPath): string
    {
        return 'https://' . $this->pullZoneHostname() . '/' . trim($videoId, '/') . '/' . ltrim($assetPath, '/');
    }

    private function resolveMp4FallbackUrl(string $videoId, array $video): ?string
    {
        if (! (bool) ($video['hasMP4Fallback'] ?? false)) {
            return null;
        }

        $resolutions = $this->parseAvailableResolutions((string) ($video['availableResolutions'] ?? ''));
        $height = $resolutions[0] ?? (isset($video['height']) ? (int) $video['height'] : null);
        if (! $height) {
            return null;
        }

        return $this->streamAssetUrl($videoId, 'play_' . $height . 'p.mp4');
    }

    private function resolveOriginalFileUrl(string $videoId, array $video): ?string
    {
        if (! (bool) ($video['hasOriginal'] ?? false)) {
            return null;
        }

        return $this->streamAssetUrl($videoId, 'original');
    }

    /**
     * @return array<int, int>
     */
    private function parseAvailableResolutions(string $value): array
    {
        preg_match_all('/(\d{3,4})p/i', $value, $matches);
        $resolutions = array_map('intval', $matches[1] ?? []);
        $resolutions = array_values(array_unique(array_filter($resolutions)));
        rsort($resolutions);

        return $resolutions;
    }

    private function statusLabel(?int $status): string
    {
        return match ($status) {
            0 => 'queued',
            1 => 'processing',
            2 => 'encoding',
            3 => 'finished',
            4 => 'resolution_finished',
            5 => 'failed',
            6 => 'presigned_upload_started',
            7 => 'presigned_upload_finished',
            8 => 'presigned_upload_failed',
            9 => 'captions_generated',
            10 => 'title_or_description_generated',
            default => 'unknown',
        };
    }

    private function videoPath(string $videoId, bool $includeUploadQuery = false): string
    {
        $path = '/library/' . $this->libraryId() . '/videos/' . $videoId;
        $enabledResolutions = trim((string) config('services.bunny_stream.enabled_resolutions', ''));
        if ($includeUploadQuery && $enabledResolutions !== '') {
            $path .= '?' . http_build_query(['enabledResolutions' => $enabledResolutions]);
        }

        return $path;
    }

    private function client(bool $withRetry = true): PendingRequest
    {
        $request = Http::baseUrl(rtrim((string) config('services.bunny_stream.api_base_url', 'https://video.bunnycdn.com'), '/'))
            ->acceptJson()
            ->withHeaders(['AccessKey' => $this->apiKey()])
            ->connectTimeout(max(1, (int) config('services.bunny_stream.connect_timeout', 15)))
            ->timeout(max(1, (int) config('services.bunny_stream.timeout', 300)));

        $retryTimes = max(0, (int) config('services.bunny_stream.retry_times', 1));
        if ($withRetry && $retryTimes > 0) {
            $request = $request->retry($retryTimes, max(0, (int) config('services.bunny_stream.retry_sleep_ms', 800)));
        }

        return $request;
    }

    private function normalizeResponse(Response $response): array
    {
        $body = $response->json();
        $body = is_array($body) ? $body : ['raw' => $response->body()];

        $message = $body['message'] ?? $body['Message'] ?? $body['error'] ?? $body['Error'] ?? null;
        $error = null;
        if (! $response->successful()) {
            $error = is_string($message) ? $message : 'Bunny Stream request failed.';
        } elseif (array_key_exists('success', $body) && $body['success'] === false) {
            $error = is_string($message) ? $message : 'Bunny Stream request was not successful.';
        }

        return [
            'ok' => $response->successful() && $error === null,
            'status_code' => $response->status(),
            'error' => $error,
            'data' => $body,
            'body' => $body,
        ];
    }

    private function configurationError(): array
    {
        return [
            'ok' => false,
            'status_code' => 500,
            'error' => 'Bunny Stream is not configured. Set BUNNY_STREAM_ENABLED, BUNNY_STREAM_API_KEY, BUNNY_STREAM_LIBRARY_ID and BUNNY_STREAM_PULL_ZONE_HOSTNAME.',
            'data' => null,
            'body' => null,
        ];
    }

    private function libraryId(): string
    {
        return trim((string) config('services.bunny_stream.library_id', ''));
    }

    private function apiKey(): string
    {
        return trim((string) config('services.bunny_stream.api_key', ''));
    }

    private function pullZoneHostname(): string
    {
        $host = trim((string) config('services.bunny_stream.pull_zone_hostname', ''));
        $host = preg_replace('#^https?://#i', '', $host) ?? $host;

        return strtolower(trim($host, "/ \t\n\r\0\x0B"));
    }

    private function looksLikeGuid(string $value): bool
    {
        return (bool) preg_match('/^[a-f0-9-]{32,64}$/i', $value);
    }
}
