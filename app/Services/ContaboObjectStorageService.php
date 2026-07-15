<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ContaboObjectStorageService
{
    private const VIDEO_EXTENSIONS = ['mp4', 'm4v', 'mov', 'mkv', 'webm', 'avi', 'mpeg', 'mpg', 'ts', 'm3u8'];

    private ?string $lastCredentialDiscoveryError = null;

    public function isConfigured(): bool
    {
        $this->ensureRuntimeDiskCredentials();

        $disk = config('filesystems.disks.' . $this->diskName(), []);

        return (bool) config('services.contabo_object_storage.enabled', false)
            && filled($disk['key'] ?? null)
            && filled($disk['secret'] ?? null)
            && filled($disk['bucket'] ?? null)
            && filled($disk['endpoint'] ?? null);
    }

    public function configurationError(): string
    {
        if (! (bool) config('services.contabo_object_storage.enabled', false)) {
            return 'Contabo Object Storage is disabled in the active .env. Set CONTABO_OBJECT_STORAGE_ENABLED=true and run php artisan config:clear.';
        }

        $disk = config('filesystems.disks.' . $this->diskName(), []);
        $missing = [];

        if (! filled($disk['bucket'] ?? null)) {
            $missing[] = 'CONTABO_OBJECT_STORAGE_BUCKET';
        }
        if (! filled($disk['endpoint'] ?? null)) {
            $missing[] = 'CONTABO_OBJECT_STORAGE_ENDPOINT';
        }

        if ($missing !== []) {
            return 'Contabo Object Storage is missing ' . implode(', ', $missing) . ' in the active .env.';
        }

        if (filled($disk['key'] ?? null) && filled($disk['secret'] ?? null)) {
            return 'Contabo Object Storage could not initialize the configured S3 disk. Check the access key, secret key, bucket, and endpoint.';
        }

        $apiConfigured = app(ContaboApiClientService::class)->isConfigured();
        if (! $apiConfigured) {
            return 'Contabo S3 keys are blank and Contabo API credentials are missing in the active .env. Set either CONTABO_OBJECT_STORAGE_ACCESS_KEY/SECRET_KEY or the four CONTABO_API_* credentials.';
        }

        if ($this->lastCredentialDiscoveryError !== null) {
            return 'Contabo API credentials are present, but S3 credential discovery failed: ' . $this->lastCredentialDiscoveryError;
        }

        return 'Contabo API credentials are present, but the app could not discover S3 credentials. Check that the API user can access Users and Object Storage credential endpoints.';
    }

    public function diskName(): string
    {
        return (string) config('services.contabo_object_storage.disk', 'contabo');
    }

    public function bucket(): string
    {
        return (string) config('services.contabo_object_storage.bucket', 'nbx');
    }

    public function endpoint(): string
    {
        return rtrim((string) config('services.contabo_object_storage.endpoint', 'https://usc1.contabostorage.com'), '/');
    }

    public function publicBaseUrl(): string
    {
        $configured = trim((string) config('services.contabo_object_storage.public_url', ''));

        if ($configured !== '') {
            return rtrim($configured, '/');
        }

        return $this->endpoint() . '/' . $this->bucket();
    }

    public function isContaboPublicUrl(string $url): bool
    {
        $normalized = rtrim(trim($url), '/');
        $base = $this->publicBaseUrl();

        return $normalized === $base || str_starts_with($normalized, $base . '/');
    }

    public function objectKeyFromPublicUrl(string $url): ?string
    {
        if (! $this->isContaboPublicUrl($url)) {
            return null;
        }

        $base = $this->publicBaseUrl();
        $key = ltrim(substr(trim($url), strlen($base)), '/');

        return $key !== '' ? rawurldecode($key) : null;
    }

    public function publicUrl(string $key): string
    {
        $segments = array_map('rawurlencode', explode('/', ltrim($this->normalizeKey($key), '/')));

        return $this->publicBaseUrl() . '/' . implode('/', $segments);
    }

    public function normalizeKey(string $key): string
    {
        $normalized = preg_replace('#/+#', '/', trim($key)) ?: '';

        return ltrim($normalized, '/');
    }

    public function buildObjectKey(
        string $filename,
        string $assetType,
        string $sourceableType,
        int $sourceableId,
        ?string $quality = null,
        ?string $format = null
    ): string {
        $prefix = trim((string) config('services.contabo_object_storage.path_prefix', 'videos'), '/');
        $group = $sourceableType === 'App\Models\Episode' || $assetType === 'episode' ? 'episodes' : 'movies';
        $extension = $this->resolveSafeVideoExtension(
            strtolower((string) pathinfo($filename, PATHINFO_EXTENSION)),
            $format
        );
        $baseName = pathinfo($filename, PATHINFO_FILENAME) ?: 'video';
        $baseName = Str::slug(Str::ascii($baseName));

        if ($baseName === '') {
            $baseName = 'video';
        }

        $qualityPart = $quality && $quality !== 'auto' ? '-' . Str::slug(Str::ascii($quality)) : '';
        $safeName = $baseName . $qualityPart . '-' . now()->format('YmdHis') . '-' . Str::lower(Str::random(8)) . '.' . $extension;

        return $this->normalizeKey(trim($prefix . '/' . $group . '/' . $sourceableId . '/' . $safeName, '/'));
    }

    /**
     * @return array{ok: bool, key?: string, public_url?: string, file_size?: int|null, error?: string|null}
     */
    public function uploadLocalFile(string $absolutePath, string $filename, array $context = []): array
    {
        if (! $this->isConfigured()) {
            return [
                'ok' => false,
                'error' => $this->configurationError(),
            ];
        }

        if (! is_file($absolutePath)) {
            return [
                'ok' => false,
                'error' => 'The source file could not be found before uploading to Contabo Object Storage.',
            ];
        }

        $key = $context['key'] ?? $this->buildObjectKey(
            $filename,
            (string) ($context['asset_type'] ?? 'movie'),
            (string) ($context['sourceable_type'] ?? 'App\Models\Movie'),
            (int) ($context['sourceable_id'] ?? 0),
            isset($context['quality']) ? (string) $context['quality'] : null,
            isset($context['format']) ? (string) $context['format'] : null
        );

        $stream = fopen($absolutePath, 'rb');

        if ($stream === false) {
            return [
                'ok' => false,
                'error' => 'Unable to open the source file for upload.',
            ];
        }

        try {
            $stored = Storage::disk($this->diskName())->put($key, $stream, [
                'visibility' => (string) config('services.contabo_object_storage.visibility', 'public'),
            ]);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if (! $stored) {
            return [
                'ok' => false,
                'error' => 'Contabo Object Storage upload failed.',
            ];
        }

        return [
            'ok' => true,
            'key' => $key,
            'public_url' => $this->publicUrl($key),
            'file_size' => filesize($absolutePath) ?: null,
            'error' => null,
        ];
    }

    /**
     * @return array{ok: bool, key?: string, public_url?: string, file_size?: int|null, error?: string|null}
     */
    public function fetchUrlToBucket(
        string $sourceUrl,
        string $sourceableType,
        int $sourceableId,
        string $assetType,
        string $quality = 'auto',
        string $format = 'auto'
    ): array {
        if ($this->isContaboPublicUrl($sourceUrl)) {
            return [
                'ok' => true,
                'key' => $this->objectKeyFromPublicUrl($sourceUrl),
                'public_url' => $sourceUrl,
                'file_size' => null,
                'error' => null,
            ];
        }

        if (! $this->isConfigured()) {
            return [
                'ok' => false,
                'error' => $this->configurationError(),
            ];
        }

        try {
            $client = new \GuzzleHttp\Client([
                'connect_timeout' => (int) config('services.contabo_object_storage.connect_timeout', 30),
                'timeout' => (int) config('services.contabo_object_storage.timeout', 1200),
            ]);

            $response = $client->request('GET', $sourceUrl, [
                'stream' => true,
                'http_errors' => false,
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode < 200 || $statusCode >= 300) {
                return [
                    'ok' => false,
                    'error' => 'Remote video fetch failed with HTTP ' . $statusCode . '.',
                ];
            }

            $filename = $this->filenameFromUrl($sourceUrl, $response->getHeaderLine('Content-Type'), $format);
            $key = $this->buildObjectKey($filename, $assetType, $sourceableType, $sourceableId, $quality, $format);
            $body = $response->getBody();
            $resource = $body->detach();

            if (! is_resource($resource)) {
                return [
                    'ok' => false,
                    'error' => 'Remote video response could not be streamed.',
                ];
            }

            try {
                $stored = Storage::disk($this->diskName())->put($key, $resource, [
                    'visibility' => (string) config('services.contabo_object_storage.visibility', 'public'),
                ]);
            } finally {
                if (is_resource($resource)) {
                    fclose($resource);
                }
            }

            if (! $stored) {
                return [
                    'ok' => false,
                    'error' => 'Contabo Object Storage upload failed.',
                ];
            }

            $contentLength = $response->getHeaderLine('Content-Length');

            return [
                'ok' => true,
                'key' => $key,
                'public_url' => $this->publicUrl($key),
                'file_size' => is_numeric($contentLength) ? (int) $contentLength : null,
                'error' => null,
            ];
        } catch (\Throwable $exception) {
            return [
                'ok' => false,
                'error' => 'Remote video fetch failed: ' . $exception->getMessage(),
            ];
        }
    }

    /**
     * @return array<int, array{key: string, url: string, size?: int|null, last_modified?: mixed}>
     */
    public function listObjects(string $prefix = '', int $limit = 50): array
    {
        $this->ensureRuntimeDiskCredentials();

        $objects = [];
        $files = Storage::disk($this->diskName())->allFiles(trim($prefix, '/'));

        foreach (array_slice($files, 0, max(1, $limit)) as $file) {
            $objects[] = [
                'key' => $file,
                'url' => $this->publicUrl($file),
                'size' => Storage::disk($this->diskName())->size($file),
                'last_modified' => Storage::disk($this->diskName())->lastModified($file),
            ];
        }

        return $objects;
    }

    private function ensureRuntimeDiskCredentials(): void
    {
        $diskName = $this->diskName();
        $disk = config('filesystems.disks.' . $diskName, []);

        if (filled($disk['key'] ?? null) && filled($disk['secret'] ?? null)) {
            return;
        }

        $credentialResult = app(ContaboApiClientService::class)->getS3Credentials();

        if (! ($credentialResult['ok'] ?? false) || ! is_array($credentialResult['data'] ?? null)) {
            $this->lastCredentialDiscoveryError = (string) ($credentialResult['error'] ?? 'No credential details returned by Contabo.');
            return;
        }

        $credentials = $credentialResult['data'];
        $accessKey = (string) ($credentials['accessKey'] ?? '');
        $secretKey = (string) ($credentials['secretKey'] ?? '');

        if ($accessKey === '' || $secretKey === '') {
            $this->lastCredentialDiscoveryError = 'Contabo returned a credential record without accessKey or secretKey.';
            return;
        }

        $this->lastCredentialDiscoveryError = null;

        config([
            'filesystems.disks.' . $diskName . '.key' => $accessKey,
            'filesystems.disks.' . $diskName . '.secret' => $secretKey,
        ]);

        try {
            Storage::forgetDisk($diskName);
        } catch (\Throwable) {
            //
        }
    }

    private function filenameFromUrl(string $url, ?string $contentType = null, ?string $fallbackFormat = null): string
    {
        $extensionFromQuery = $this->extensionFromQueryString($url);
        if ($extensionFromQuery !== null) {
            $basenameFromQuery = $this->basenameFromQueryString($url) ?? ('video-' . now()->format('YmdHis'));
            $baseNameOnly = pathinfo($basenameFromQuery, PATHINFO_FILENAME) ?: $basenameFromQuery;
            return $baseNameOnly . '.' . $extensionFromQuery;
        }

        $path = (string) (parse_url($url, PHP_URL_PATH) ?: '');
        $filename = $this->sanitizeFilename(basename($path));

        if ($filename !== null && $this->hasGenericScriptExtension($filename)) {
            $filename = null;
        }

        if ($filename === null) {
            $query = (string) (parse_url($url, PHP_URL_QUERY) ?: '');
            if ($query !== '') {
                parse_str($query, $params);
                foreach (['file', 'filename', 'name', 'title', 'download', 'url', 'path'] as $key) {
                    $candidate = $this->sanitizeFilename(basename((string) ($params[$key] ?? '')));
                    if ($candidate !== null && $this->hasAllowedVideoExtension($candidate)) {
                        $filename = $candidate;
                        break;
                    }
                }
            }
        }

        if ($filename === null || ! str_contains($filename, '.') || ! $this->hasAllowedVideoExtension($filename)) {
            $extension = $this->resolveSafeVideoExtension(
                $this->extensionFromContentType($contentType),
                $fallbackFormat
            );
            $filename = 'video-' . now()->format('YmdHis') . '.' . strtolower($extension);
        }

        return $filename;
    }

    private function extensionFromQueryString(string $url): ?string
    {
        $query = (string) (parse_url($url, PHP_URL_QUERY) ?: '');
        if ($query === '') {
            return null;
        }

        parse_str($query, $params);
        foreach (['file', 'filename', 'name', 'title', 'download', 'url', 'path'] as $key) {
            $value = $params[$key] ?? null;
            if (! is_string($value) || trim($value) === '') {
                continue;
            }

            $extension = strtolower((string) pathinfo(basename($value), PATHINFO_EXTENSION));
            if ($extension !== '' && in_array($extension, self::VIDEO_EXTENSIONS, true)) {
                return $extension;
            }
        }

        return null;
    }

    private function basenameFromQueryString(string $url): ?string
    {
        $query = (string) (parse_url($url, PHP_URL_QUERY) ?: '');
        if ($query === '') {
            return null;
        }

        parse_str($query, $params);
        foreach (['file', 'filename', 'name', 'title', 'download', 'url', 'path'] as $key) {
            $candidate = $this->sanitizeFilename(basename((string) ($params[$key] ?? '')));
            if ($candidate !== null && $this->hasAllowedVideoExtension($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function sanitizeFilename(?string $filename): ?string
    {
        if (! is_string($filename) || trim($filename) === '') {
            return null;
        }

        $decoded = urldecode($filename);
        $decoded = $this->replaceEmbeddedDomainsWithWhiteLabel($decoded);
        $clean = preg_replace('/[^A-Za-z0-9._-]/', '_', $decoded) ?: '';
        $clean = preg_replace('/_+/', '_', $clean) ?: '';
        $clean = ltrim($clean, '.');
        $clean = trim($clean, '_');

        return $clean !== '' ? $clean : null;
    }

    private function replaceEmbeddedDomainsWithWhiteLabel(string $value): string
    {
        return preg_replace(
            '/(?<![A-Za-z0-9])(?:www\.)?(?:[A-Za-z0-9-]+\.)+[A-Za-z]{2,}(?![A-Za-z0-9])/iu',
            'naraboxtv.com',
            $value
        ) ?: $value;
    }

    private function hasGenericScriptExtension(string $filename): bool
    {
        $extension = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));

        return in_array($extension, ['php', 'asp', 'aspx', 'jsp', 'cgi', 'cfm', 'pl', 'py'], true);
    }

    private function hasAllowedVideoExtension(string $filename): bool
    {
        $extension = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));

        return $extension !== '' && in_array($extension, self::VIDEO_EXTENSIONS, true);
    }

    private function resolveSafeVideoExtension(?string $primary, ?string $fallback = null): string
    {
        $primary = strtolower(trim((string) $primary));
        if ($primary !== '' && in_array($primary, self::VIDEO_EXTENSIONS, true)) {
            return $primary;
        }

        $fallback = strtolower(trim((string) $fallback));
        if ($fallback !== '' && $fallback !== 'auto' && in_array($fallback, self::VIDEO_EXTENSIONS, true)) {
            return $fallback;
        }

        return 'mp4';
    }

    private function extensionFromContentType(?string $contentType): ?string
    {
        $normalized = strtolower(trim((string) $contentType));

        return match (true) {
            str_contains($normalized, 'video/mp4') => 'mp4',
            str_contains($normalized, 'video/webm') => 'webm',
            str_contains($normalized, 'video/x-matroska') => 'mkv',
            str_contains($normalized, 'application/vnd.apple.mpegurl') => 'm3u8',
            default => null,
        };
    }
}
