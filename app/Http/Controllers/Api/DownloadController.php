<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DownloadSource;
use App\Services\ContaboObjectStorageService;
use App\Services\MediaAccessService;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * @group Player & Downloads
 *
 * Download file by download source id (from player response). Access: free or user has purchase/rental/subscription.
 */
class DownloadController extends Controller
{
    public function __construct(
        private readonly MediaAccessService $mediaAccess,
        private readonly ContaboObjectStorageService $contaboStorage,
    ) {}

    private function restrictedPayload(): array
    {
        return [
            'code' => 'CONTENT_RESTRICTED',
            'status' => 'dmca_removed',
            'title' => 'Content unavailable',
            'message' => 'This title has been restricted following a copyright or compliance request received by NaraboxTV. We take intellectual property and platform safety seriously and review all reports in accordance with our compliance process.',
            'actions' => ['copyright_policy', 'contact_support'],
        ];
    }

    /**
     * Exchange an authenticated API request for a short-lived download URL.
     *
     * Browsers and native Linking calls cannot attach the Bearer header used to
     * load the player manifest. A temporary signed URL preserves the entitlement
     * decision without putting the user's long-lived access token in the URL.
     */
    public function authorizeDownload(Request $request, $id)
    {
        $downloadSource = DownloadSource::findOrFail($id);
        $downloadable = $this->validatedDownloadable($downloadSource);
        if ($downloadable instanceof \Illuminate\Http\JsonResponse) {
            return $downloadable;
        }

        $user = Auth::guard('sanctum')->user();
        $denial = $this->accessDenial($downloadable, $user);
        if ($denial !== null) {
            return response()->json($denial['body'], $denial['status']);
        }

        $expiresAt = now()->addMinutes(5);
        $downloadUrl = $this->temporaryObjectStorageUrl($downloadSource, $expiresAt)
            ?? URL::temporarySignedRoute(
                'downloads.file',
                $expiresAt,
                ['id' => $downloadSource->id]
            );

        return response()->json([
            'download_url' => $downloadUrl,
            'expires_at' => $expiresAt->toIso8601String(),
            'label' => $downloadSource->label,
            'format' => $downloadSource->format,
            'file_size' => $downloadSource->file_size,
        ])->header('Cache-Control', 'no-store, private');
    }

    /**
     * Download file. id = download source id. Auth is optional for free content;
     * paid content requires a Bearer-authenticated request or a temporary URL
     * issued by authorizeDownload().
     */
    public function download(Request $request, $id)
    {
        $downloadSource = DownloadSource::findOrFail($id);
        $downloadable = $this->validatedDownloadable($downloadSource);
        if ($downloadable instanceof \Illuminate\Http\JsonResponse) {
            return $downloadable;
        }

        if ($request->hasValidSignature()) {
            return $this->serveFile($downloadSource);
        }

        if ($request->has('signature')) {
            return response()->json([
                'code' => 'DOWNLOAD_LINK_EXPIRED',
                'error' => 'This download link has expired.',
                'message' => 'Return to the title and request the download again.',
                'link_expired' => true,
            ], 403);
        }

        // Resolve user from Bearer token (route has no auth middleware)
        $user = Auth::guard('sanctum')->user();
        $denial = $this->accessDenial($downloadable, $user);
        if ($denial !== null) {
            return response()->json($denial['body'], $denial['status']);
        }

        return $this->serveFile($downloadSource);
    }

    private function validatedDownloadable(DownloadSource $downloadSource): mixed
    {
        if (! $downloadSource->is_active) {
            return response()->json([
                'code' => 'DOWNLOAD_SOURCE_UNAVAILABLE',
                'error' => 'Download source is not available',
                'message' => 'This file is temporarily unavailable. Try another quality or try again later.',
                'retryable' => true,
            ], 404);
        }

        $sourceContent = $downloadSource->downloadable;
        if (! $sourceContent) {
            return response()->json([
                'code' => 'DOWNLOAD_SOURCE_UNAVAILABLE',
                'error' => 'Content not found',
                'message' => 'The title attached to this download could not be found.',
            ], 404);
        }

        $media = $this->mediaAccess->resolveMedia($sourceContent);
        if (! $media) {
            return response()->json([
                'code' => 'DOWNLOAD_SOURCE_UNAVAILABLE',
                'error' => 'Content not found',
                'message' => 'The title attached to this download could not be found.',
            ], 404);
        }

        if (($sourceContent->content_status ?? 'published') === 'dmca_removed'
            || ($media->content_status ?? 'published') === 'dmca_removed') {
            return response()->json($this->restrictedPayload(), 403);
        }

        if ($sourceContent->download_enabled === false || $media->download_enabled === false) {
            return response()->json([
                'code' => 'DOWNLOADS_DISABLED',
                'error' => 'Downloads are not enabled for this title.',
                'message' => 'You can continue streaming this title while downloads are unavailable.',
                'downloads_disabled' => true,
            ], 403);
        }

        return $media;
    }

    /**
     * @return array{status:int,body:array<string,mixed>}|null
     */
    private function accessDenial(mixed $downloadable, mixed $user): ?array
    {
        $result = $this->mediaAccess->evaluate($downloadable, $user);
        if ((bool) ($result['has_access'] ?? false)) {
            return null;
        }

        $status = (int) ($result['http_status'] ?? 403);
        unset($result['http_status']);

        return [
            'status' => $status,
            'body' => array_merge($result, [
                'error' => $result['reason'] ?? 'Access denied',
                'message' => $result['message'] ?? $result['reason'] ?? 'Access denied',
            ]),
        ];
    }

    private function temporaryObjectStorageUrl(DownloadSource $downloadSource, mixed $expiresAt): ?string
    {
        $remoteUrl = $this->remoteUrl($downloadSource);
        if (! $remoteUrl || ! $this->contaboStorage->isContaboPublicUrl($remoteUrl)) {
            return null;
        }

        $objectKey = $this->contaboStorage->objectKeyFromPublicUrl($remoteUrl);
        if (! $objectKey || ! $this->contaboStorage->isConfigured()) {
            return null;
        }

        try {
            return Storage::disk($this->contaboStorage->diskName())->temporaryUrl(
                $objectKey,
                $expiresAt,
                [
                    'ResponseContentDisposition' => 'attachment; filename="'.$this->downloadFilename($downloadSource, $remoteUrl).'"',
                    'ResponseContentType' => 'application/octet-stream',
                ],
            );
        } catch (\Throwable $error) {
            Log::warning('download.temporary_object_url_failed', [
                'download_source_id' => $downloadSource->id,
                'host' => parse_url($remoteUrl, PHP_URL_HOST),
                'exception' => $error::class,
                'message' => $error->getMessage(),
            ]);

            return null;
        }
    }

    private function remoteUrl(DownloadSource $downloadSource): ?string
    {
        foreach ([$downloadSource->url, $downloadSource->file_path] as $candidate) {
            if (! is_string($candidate) || trim($candidate) === '') {
                continue;
            }

            $candidate = trim($candidate);
            if (str_starts_with($candidate, 'http://') || str_starts_with($candidate, 'https://')) {
                return $this->normalizeRemoteUrl($candidate);
            }

            if ($downloadSource->type === 'url') {
                return $this->normalizeRemoteUrl('https://'.$candidate);
            }
        }

        return null;
    }

    private function downloadFilename(DownloadSource $downloadSource, ?string $url = null): string
    {
        $path = $url ? (string) parse_url($url, PHP_URL_PATH) : (string) $downloadSource->file_path;
        $extension = $downloadSource->format ?: pathinfo($path, PATHINFO_EXTENSION) ?: 'mp4';
        $baseName = $downloadSource->label ?: pathinfo($path, PATHINFO_FILENAME) ?: 'download';
        $safeBaseName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $baseName) ?: 'download';
        $safeExtension = preg_replace('/[^a-zA-Z0-9]/', '', $extension) ?: 'mp4';

        return $safeBaseName.' - naraboxtv.com.'.$safeExtension;
    }

    /**
     * Serve the file with proper headers
     * Aggressively tries multiple methods: URL redirect, local file, or fetched file
     */
    private function serveFile(DownloadSource $downloadSource)
    {
        // PRIORITY 1: If type is 'url' or URL field exists, proxy the URL so the browser
        // receives Content-Disposition: attachment instead of opening the media player.
        if ($downloadSource->type === 'url' || ! empty($downloadSource->url)) {
            $url = $downloadSource->url;
            if ($url) {
                if (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
                    $url = 'https://'.$url;
                }

                return $this->streamRemoteUrl($url, $downloadSource);
            }
        }

        // PRIORITY 2: file_path as URL - proxy instead of redirecting to a streamable URL.
        if (! empty($downloadSource->file_path)) {
            $filePath = $downloadSource->file_path;
            if (str_starts_with($filePath, 'http://') || str_starts_with($filePath, 'https://')) {
                return $this->streamRemoteUrl($filePath, $downloadSource);
            }
        }

        // PRIORITY 3: For local files, try multiple path formats
        if (empty($downloadSource->file_path)) {
            return response()->json([
                'code' => 'DOWNLOAD_SOURCE_UNAVAILABLE',
                'error' => 'File path not configured',
                'message' => 'This download is temporarily unavailable. Try another quality or try again later.',
                'retryable' => true,
            ], 404);
        }

        // Handle different path formats - try all possible locations
        $filePath = null;
        $pathsToTry = [
            // Try public storage
            Storage::disk('public')->path($downloadSource->file_path),
            // Try absolute path as-is
            $downloadSource->file_path,
            // Try storage path
            storage_path('app/public/'.$downloadSource->file_path),
            // Try public directory
            public_path($downloadSource->file_path),
            // Try storage/app
            storage_path('app/'.$downloadSource->file_path),
            // Try removing storage/ prefix if present
            str_replace('storage/', '', $downloadSource->file_path),
            // Try with public/storage prefix
            public_path('storage/'.$downloadSource->file_path),
        ];

        foreach ($pathsToTry as $path) {
            if ($path && file_exists($path) && is_file($path)) {
                $filePath = $path;
                break;
            }
        }

        if (! $filePath || ! file_exists($filePath)) {
            // Last resort: if file_path might be a relative URL, try to construct full URL
            if (! str_contains($downloadSource->file_path, '://')) {
                $baseUrl = config('app.url');
                $relativePath = ltrim($downloadSource->file_path, '/');
                $fullUrl = $this->normalizeRemoteUrl(rtrim($baseUrl, '/').'/'.$relativePath);

                return $this->streamRemoteUrl($fullUrl, $downloadSource);
            }

            return response()->json([
                'code' => 'DOWNLOAD_SOURCE_UNAVAILABLE',
                'error' => 'File not found on server',
                'message' => 'This download is temporarily unavailable. Try another quality or try again later.',
                'retryable' => true,
            ], 404);
        }

        // Determine filename
        $filename = basename($downloadSource->file_path);
        if ($downloadSource->label) {
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $filename = $downloadSource->label.'.'.$extension;
        }

        // Clean filename for download
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);

        // Add naraboxtv.com branding before the extension
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $nameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);
        $filename = $nameWithoutExt.' - naraboxtv.com.'.$extension;

        return response()->download($filePath, $filename, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Stream a remote URL to the client with Content-Disposition: attachment so the browser downloads instead of following redirect (which would play, not download).
     */
    private function streamRemoteUrl(string $url, DownloadSource $downloadSource)
    {
        $url = $this->normalizeRemoteUrl($url);
        $filename = $this->downloadFilename($downloadSource, $url);
        $requiresIpv4Fallback = $this->isCdnUrl($url)
            || $this->contaboStorage->isContaboPublicUrl($url);

        $attempts = $requiresIpv4Fallback
            ? ['default', 'v4']
            : ['default'];

        $response = null;
        $lastError = null;

        foreach ($attempts as $attempt) {
            $clientConfig = [
                'timeout' => 600,
                'connect_timeout' => 30,
                'allow_redirects' => true,
            ];

            if ($attempt === 'v4' || $attempt === 'v6') {
                $clientConfig['force_ip_resolve'] = $attempt;
            }

            $client = new GuzzleClient($clientConfig);

            try {
                $response = $client->request('GET', $url, ['stream' => true]);
                $lastError = null;
                break;
            } catch (\Throwable $error) {
                $lastError = $error;
            }
        }

        if (! $response) {
            Log::warning('download.remote_source_unavailable', [
                'download_source_id' => $downloadSource->id,
                'host' => parse_url($url, PHP_URL_HOST),
                'exception' => $lastError ? $lastError::class : null,
                'message' => $lastError?->getMessage(),
            ]);

            return response()->json([
                'code' => 'DOWNLOAD_SOURCE_UNAVAILABLE',
                'error' => 'Download source is temporarily unavailable.',
                'message' => 'We could not start this download. Try again shortly or choose another quality.',
                'retryable' => true,
            ], 502);
        }

        $body = $response->getBody();
        $contentType = $response->getHeaderLine('Content-Type') ?: 'application/octet-stream';

        return response()->streamDownload(function () use ($body) {
            while (! $body->eof()) {
                echo $body->read(65536);
                if (ob_get_level()) {
                    ob_flush();
                }
                flush();
            }
        }, $filename, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function normalizeRemoteUrl(string $url): string
    {
        $parts = parse_url($url);
        if (! is_array($parts)) {
            return $url;
        }

        $scheme = $parts['scheme'] ?? null;
        $host = $parts['host'] ?? null;
        if (! is_string($scheme) || ! is_string($host)) {
            return $url;
        }

        $normalizedPath = preg_replace('#/+#', '/', (string) ($parts['path'] ?? '/')) ?: '/';
        $normalizedPath = '/'.ltrim($normalizedPath, '/');

        $rebuilt = $scheme.'://';
        if (isset($parts['user'])) {
            $rebuilt .= $parts['user'];
            if (isset($parts['pass'])) {
                $rebuilt .= ':'.$parts['pass'];
            }
            $rebuilt .= '@';
        }

        $rebuilt .= $host;

        if (isset($parts['port'])) {
            $rebuilt .= ':'.$parts['port'];
        }

        $rebuilt .= $normalizedPath;

        if (isset($parts['query'])) {
            $rebuilt .= '?'.$parts['query'];
        }

        if (isset($parts['fragment'])) {
            $rebuilt .= '#'.$parts['fragment'];
        }

        return $rebuilt;
    }

    private function isCdnUrl(string $url): bool
    {
        $cdnBase = rtrim((string) config('services.cdn.base_url', ''), '/');
        if ($cdnBase === '') {
            return false;
        }

        $urlHost = (string) (parse_url($url, PHP_URL_HOST) ?: '');
        $cdnHost = (string) (parse_url($cdnBase, PHP_URL_HOST) ?: '');

        return $urlHost !== '' && $cdnHost !== '' && strcasecmp($urlHost, $cdnHost) === 0;
    }
}
