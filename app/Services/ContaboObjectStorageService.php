<?php

namespace App\Services;

use App\Services\Storage\StorageTarget;
use App\Services\Storage\StorageTargetRegistry;
use App\Services\Storage\StorageUsageService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Direct-upload/fetch/URL-generation gateway for ONE Contabo storage target.
 * By default this resolves to the legacy target (contabo_nbx) so existing
 * callers of app(ContaboObjectStorageService::class) keep working unchanged.
 * Call ->forTarget($key) to operate against a different logical target
 * (e.g. contabo_nb_nbx) without touching any other call site.
 */
class ContaboObjectStorageService
{
    private const VIDEO_EXTENSIONS = ['mp4', 'm4v', 'mov', 'mkv', 'webm', 'avi', 'mpeg', 'mpg', 'ts', 'm3u8'];

    private ?string $lastCredentialDiscoveryError = null;

    private StorageTarget $target;

    public function __construct(
        private readonly StorageTargetRegistry $registry,
        private readonly StorageUsageService $usageService,
        ?string $targetKey = null,
    ) {
        $this->target = $this->registry->findOrFail($this->registry->normalizeStoredKey($targetKey));
    }

    public function forTarget(string $targetKey): self
    {
        return new self($this->registry, $this->usageService, $targetKey);
    }

    public function target(): StorageTarget
    {
        return $this->target;
    }

    public function targetKey(): string
    {
        return $this->target->key;
    }

    public function isConfigured(): bool
    {
        $this->ensureRuntimeDiskCredentials();

        $disk = config('filesystems.disks.'.$this->diskName(), []);

        return $this->target->enabled
            && filled($disk['key'] ?? null)
            && filled($disk['secret'] ?? null)
            && filled($disk['bucket'] ?? null)
            && filled($disk['endpoint'] ?? null);
    }

    public function configurationError(): string
    {
        if (! $this->target->enabled) {
            return "Storage target [{$this->target->label}] is disabled in the active .env. Enable it and run php artisan config:clear.";
        }

        $disk = config('filesystems.disks.'.$this->diskName(), []);
        $missing = [];

        if (! filled($disk['bucket'] ?? null)) {
            $missing[] = 'bucket';
        }
        if (! filled($disk['endpoint'] ?? null)) {
            $missing[] = 'endpoint';
        }

        if ($missing !== []) {
            return "Storage target [{$this->target->label}] is missing ".implode(', ', $missing).' configuration in the active .env.';
        }

        if (filled($disk['key'] ?? null) && filled($disk['secret'] ?? null)) {
            return "Storage target [{$this->target->label}] could not initialize the configured S3 disk. Check the access key, secret key, bucket, and endpoint.";
        }

        if ($this->target->key !== $this->registry->legacyKey()) {
            return "Storage target [{$this->target->label}] is missing S3 access credentials in the active .env.";
        }

        $apiConfigured = app(ContaboApiClientService::class)->isConfigured();
        if (! $apiConfigured) {
            return 'Contabo S3 keys are blank and Contabo API credentials are missing in the active .env. Set either CONTABO_OBJECT_STORAGE_ACCESS_KEY/SECRET_KEY or the four CONTABO_API_* credentials.';
        }

        if ($this->lastCredentialDiscoveryError !== null) {
            return 'Contabo API credentials are present, but S3 credential discovery failed: '.$this->lastCredentialDiscoveryError;
        }

        return 'Contabo API credentials are present, but the app could not discover S3 credentials. Check that the API user can access Users and Object Storage credential endpoints.';
    }

    public function diskName(): string
    {
        return $this->target->disk;
    }

    public function bucket(): string
    {
        return $this->target->bucket;
    }

    public function endpoint(): string
    {
        return $this->target->endpoint;
    }

    public function publicBaseUrl(): string
    {
        if ($this->target->publicUrl !== '') {
            return $this->target->publicUrl;
        }

        return $this->endpoint().'/'.$this->bucket();
    }

    public function isContaboPublicUrl(string $url): bool
    {
        $normalized = rtrim(trim($url), '/');
        $base = $this->publicBaseUrl();
        if ($normalized === $base || str_starts_with($normalized, $base.'/')) {
            return true;
        }

        $urlHost = strtolower((string) parse_url($normalized, PHP_URL_HOST));
        $endpointHost = strtolower((string) parse_url($this->endpoint(), PHP_URL_HOST));
        if ($urlHost === '' || $endpointHost === '' || $urlHost !== $endpointHost) {
            return false;
        }

        $firstSegment = explode('/', trim((string) parse_url($normalized, PHP_URL_PATH), '/'))[0] ?? '';
        $bucket = $this->bucket();

        return $firstSegment === $bucket || str_ends_with($firstSegment, ':'.$bucket);
    }

    public function objectKeyFromPublicUrl(string $url): ?string
    {
        if (! $this->isContaboPublicUrl($url)) {
            return null;
        }

        $base = $this->publicBaseUrl();
        if (rtrim(trim($url), '/') === $base || str_starts_with(trim($url), $base.'/')) {
            $key = ltrim(substr(trim($url), strlen($base)), '/');

            return $key !== '' ? rawurldecode($key) : null;
        }

        $segments = array_values(array_filter(explode('/', trim((string) parse_url($url, PHP_URL_PATH), '/'))));
        if ($segments === []) {
            return null;
        }
        $bucket = $this->bucket();
        if ($segments[0] === $bucket || str_ends_with($segments[0], ':'.$bucket)) {
            array_shift($segments);
        }
        $key = implode('/', $segments);

        return $key !== '' ? rawurldecode($key) : null;
    }

    public function publicUrl(string $key): string
    {
        $segments = array_map('rawurlencode', explode('/', ltrim($this->normalizeKey($key), '/')));

        return $this->publicBaseUrl().'/'.implode('/', $segments);
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
        $prefix = $this->target->pathPrefix;
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

        $qualityPart = $quality && $quality !== 'auto' ? '-'.Str::slug(Str::ascii($quality)) : '';
        $safeName = $baseName.$qualityPart.'-'.now()->format('YmdHis').'-'.Str::lower(Str::random(8)).'.'.$extension;

        return $this->normalizeKey(trim($prefix.'/'.$group.'/'.$sourceableId.'/'.$safeName, '/'));
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
                'error' => 'The source file could not be found before uploading to object storage.',
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
            $options = $this->target->provider === 'cloudflare_r2'
                ? []
                : ['visibility' => (string) config('services.contabo_object_storage.visibility', 'public')];
            $stored = Storage::disk($this->diskName())->put($key, $stream, $options);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if (! $stored) {
            return [
                'ok' => false,
                'error' => $this->target->label.' upload failed.',
            ];
        }

        $fileSize = filesize($absolutePath) ?: 0;
        if ($fileSize > 0) {
            $this->usageService->recordUpload($this->target->key, $fileSize);
        }

        return [
            'ok' => true,
            'key' => $key,
            'public_url' => $this->publicUrl($key),
            'file_size' => $fileSize ?: null,
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
                    'error' => 'Remote video fetch failed with HTTP '.$statusCode.'.',
                ];
            }

            $validationError = $this->validateRemoteVideoResponse($response, $sourceUrl, $format);
            if ($validationError !== null) {
                return [
                    'ok' => false,
                    'error' => $validationError,
                ];
            }

            $filename = $this->filenameFromUrl(
                $sourceUrl,
                $response->getHeaderLine('Content-Type'),
                $format,
                $response->getHeaderLine('Content-Disposition')
            );
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
                $options = $this->target->provider === 'cloudflare_r2'
                    ? []
                    : ['visibility' => (string) config('services.contabo_object_storage.visibility', 'public')];
                $stored = Storage::disk($this->diskName())->put($key, $resource, $options);
            } finally {
                if (is_resource($resource)) {
                    fclose($resource);
                }
            }

            if (! $stored) {
                return [
                    'ok' => false,
                    'error' => $this->target->label.' upload failed.',
                ];
            }

            $contentLength = $response->getHeaderLine('Content-Length');
            $uploadedBytes = is_numeric($contentLength) ? (int) $contentLength : 0;
            if ($uploadedBytes > 0) {
                $this->usageService->recordUpload($this->target->key, $uploadedBytes);
            }

            return [
                'ok' => true,
                'key' => $key,
                'public_url' => $this->publicUrl($key),
                'file_size' => $uploadedBytes ?: null,
                'error' => null,
            ];
        } catch (\Throwable $exception) {
            return [
                'ok' => false,
                'error' => 'Remote video fetch failed: '.$exception->getMessage(),
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
        $disk = config('filesystems.disks.'.$diskName, []);

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
            'filesystems.disks.'.$diskName.'.key' => $accessKey,
            'filesystems.disks.'.$diskName.'.secret' => $secretKey,
        ]);

        try {
            Storage::forgetDisk($diskName);
        } catch (\Throwable) {
            //
        }
    }

    private function validateRemoteVideoResponse($response, string $sourceUrl, ?string $fallbackFormat = null): ?string
    {
        $contentType = strtolower(trim((string) $response->getHeaderLine('Content-Type')));
        $contentLength = trim((string) $response->getHeaderLine('Content-Length'));
        $contentDisposition = (string) $response->getHeaderLine('Content-Disposition');
        $minBytes = max(0, (int) config('services.contabo_object_storage.min_fetch_bytes', 262144));

        if (is_numeric($contentLength) && (int) $contentLength > 0 && (int) $contentLength < $minBytes) {
            return 'Remote video fetch returned only '.$contentLength.' bytes, which is too small for a playable movie file.';
        }

        if ($this->isHtmlLikeContentType($contentType)) {
            return 'Remote video fetch returned HTML instead of video. The source link may be expired, gated, or a converter landing page.';
        }

        if ($this->isVideoLikeContentType($contentType)) {
            return null;
        }

        if ($this->filenameFromContentDisposition($contentDisposition) !== null) {
            return null;
        }

        $path = (string) (parse_url($sourceUrl, PHP_URL_PATH) ?: '');
        $filename = $this->sanitizeFilename(basename($path));
        if ($filename !== null && $this->hasAllowedVideoExtension($filename)) {
            return null;
        }

        $fallback = strtolower(trim((string) $fallbackFormat));
        if ($fallback !== '' && $fallback !== 'auto' && in_array($fallback, self::VIDEO_EXTENSIONS, true)) {
            return null;
        }

        return 'Remote video fetch did not return a recognizable video response. Content-Type was "'.($contentType ?: 'unknown').'".';
    }

    private function isHtmlLikeContentType(string $contentType): bool
    {
        return str_contains($contentType, 'text/html')
            || str_contains($contentType, 'application/xhtml+xml')
            || str_contains($contentType, 'application/json')
            || str_contains($contentType, 'text/plain');
    }

    private function isVideoLikeContentType(string $contentType): bool
    {
        if ($contentType === '') {
            return false;
        }

        return str_starts_with($contentType, 'video/')
            || str_contains($contentType, 'application/octet-stream')
            || str_contains($contentType, 'binary/octet-stream')
            || str_contains($contentType, 'application/x-download')
            || str_contains($contentType, 'application/force-download')
            || str_contains($contentType, 'application/vnd.apple.mpegurl')
            || str_contains($contentType, 'application/x-mpegurl');
    }

    private function filenameFromUrl(
        string $url,
        ?string $contentType = null,
        ?string $fallbackFormat = null,
        ?string $contentDisposition = null
    ): string {
        $filenameFromDisposition = $this->filenameFromContentDisposition($contentDisposition);
        if ($filenameFromDisposition !== null) {
            return $filenameFromDisposition;
        }

        $extensionFromQuery = $this->extensionFromQueryString($url);
        if ($extensionFromQuery !== null) {
            $basenameFromQuery = $this->basenameFromQueryString($url) ?? ('video-'.now()->format('YmdHis'));
            $baseNameOnly = pathinfo($basenameFromQuery, PATHINFO_FILENAME) ?: $basenameFromQuery;

            return $baseNameOnly.'.'.$extensionFromQuery;
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
            $filename = 'video-'.now()->format('YmdHis').'.'.strtolower($extension);
        }

        return $filename;
    }

    private function filenameFromContentDisposition(?string $contentDisposition): ?string
    {
        if (! is_string($contentDisposition) || trim($contentDisposition) === '') {
            return null;
        }

        if (preg_match("/filename\\*=UTF-8''([^;]+)/i", $contentDisposition, $matches)) {
            $candidate = $this->sanitizeFilename(rawurldecode(trim($matches[1], "\"' ")));
            if ($candidate !== null && $this->hasAllowedVideoExtension($candidate)) {
                return $candidate;
            }
        }

        if (preg_match('/filename="?([^";]+)"?/i', $contentDisposition, $matches)) {
            $candidate = $this->sanitizeFilename(trim($matches[1], "\"' "));
            if ($candidate !== null && $this->hasAllowedVideoExtension($candidate)) {
                return $candidate;
            }
        }

        return null;
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
        $decoded = preg_replace('/[\s_-]*-[\s_-]*(?=naraboxtv\.com)/i', ' ', $decoded) ?: $decoded;
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
