<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DownloadSource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use GuzzleHttp\Client as GuzzleClient;

/**
 * @group Player & Downloads
 *
 * Download file by download source id (from player response). Access: free or user has purchase/rental/subscription. Optional: Authorization header or ?access_token=.
 */
class DownloadController extends Controller
{
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
     * Download file. id = download source id. Auth optional for free content; required for paid. Supports ?access_token= for direct links.
     */
    public function download(Request $request, $id)
    {
        // Allow auth via ?access_token= for direct navigation (enables instant download for large files)
        if (!$request->header('Authorization') && $request->filled('access_token')) {
            $request->headers->set('Authorization', 'Bearer ' . $request->get('access_token'));
        }

        $downloadSource = DownloadSource::findOrFail($id);
        
        // Check if download source is active
        if (!$downloadSource->is_active) {
            return response()->json([
                'error' => 'Download source is not available',
            ], 404);
        }

        // Get the downloadable content (movie/episode)
        $downloadable = $downloadSource->downloadable;
        if (!$downloadable) {
            return response()->json([
                'error' => 'Content not found',
            ], 404);
        }

        // Platform-level restriction (DMCA/compliance). Do not serve downloads.
        if (($downloadable->content_status ?? 'published') === 'dmca_removed') {
            return response()->json($this->restrictedPayload(), 403);
        }

        // Resolve user from Bearer token (route has no auth middleware)
        $user = Auth::guard('sanctum')->user();
        
        // For free content, allow download without authentication
        $isFree = $downloadable->is_free;
        if ($isFree) {
            return $this->serveFile($downloadSource);
        }

        // If no user and content is not free, require authentication
        if (!$user) {
            return response()->json([
                'error' => 'Authentication required. Please log in to download this content.',
                'message' => 'This content requires authentication. Please log in to continue.',
                'requires_auth' => true,
            ], 401);
        }

        // Check if user has purchased
        $purchaseType = get_class($downloadable);
        $purchase = \App\Models\UserPurchase::where('user_id', $user->id)
            ->where('purchasable_type', $purchaseType)
            ->where('purchasable_id', $downloadable->id)
            ->first();

        if ($purchase) {
            return $this->serveFile($downloadSource);
        }

        // Check if user has active rental
        $rentalType = get_class($downloadable);
        $rental = \App\Models\UserRental::where('user_id', $user->id)
            ->where('rentable_type', $rentalType)
            ->where('rentable_id', $downloadable->id)
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->first();

        if ($rental) {
            return $this->serveFile($downloadSource);
        }

        // Check premium subscription
        if ($downloadable->is_premium) {
            $activeSubscription = \App\Models\UserSubscription::where('user_id', $user->id)
                ->where('status', 'ACTIVE')
                ->where('expires_at', '>', now())
                ->first();

            if ($activeSubscription) {
                return $this->serveFile($downloadSource);
            }
        }

        // No access - provide clear error message
        $errorMessage = 'You do not have access to download this content.';
        if ($downloadable->is_premium) {
            $errorMessage = 'This content requires an active premium subscription to download.';
        } elseif ($downloadable->price_rent || $downloadable->price_buy) {
            $errorMessage = 'This content requires purchase or rental to download.';
        }
        
        return response()->json([
            'error' => $errorMessage,
            'message' => $errorMessage,
            'requires_payment' => !$downloadable->is_free && !$downloadable->is_premium,
            'requires_subscription' => $downloadable->is_premium,
            'can_rent' => !empty($downloadable->price_rent),
            'can_buy' => !empty($downloadable->price_buy),
            'rent_price' => $downloadable->price_rent,
            'buy_price' => $downloadable->price_buy,
        ], 403);
    }

    /**
     * Serve the file with proper headers
     * Aggressively tries multiple methods: URL redirect, local file, or fetched file
     */
    private function serveFile(DownloadSource $downloadSource)
    {
        // PRIORITY 1: If type is 'url' or URL field exists, proxy the URL so the browser
        // receives Content-Disposition: attachment instead of opening the media player.
        if ($downloadSource->type === 'url' || !empty($downloadSource->url)) {
            $url = $downloadSource->url;
            if ($url) {
                if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
                    $url = 'https://' . $url;
                }
                return $this->streamRemoteUrl($url, $downloadSource);
            }
        }

        // PRIORITY 2: file_path as URL - proxy instead of redirecting to a streamable URL.
        if (!empty($downloadSource->file_path)) {
            $filePath = $downloadSource->file_path;
            if (str_starts_with($filePath, 'http://') || str_starts_with($filePath, 'https://')) {
                return $this->streamRemoteUrl($filePath, $downloadSource);
            }
        }

        // PRIORITY 3: For local files, try multiple path formats
        if (empty($downloadSource->file_path)) {
            return response()->json([
                'error' => 'File path not configured',
                'message' => 'The download file path is missing. Please contact support.',
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
            storage_path('app/public/' . $downloadSource->file_path),
            // Try public directory
            public_path($downloadSource->file_path),
            // Try storage/app
            storage_path('app/' . $downloadSource->file_path),
            // Try removing storage/ prefix if present
            str_replace('storage/', '', $downloadSource->file_path),
            // Try with public/storage prefix
            public_path('storage/' . $downloadSource->file_path),
        ];
        
        foreach ($pathsToTry as $path) {
            if ($path && file_exists($path) && is_file($path)) {
                $filePath = $path;
                break;
            }
        }
        
        if (!$filePath || !file_exists($filePath)) {
            // Last resort: if file_path might be a relative URL, try to construct full URL
            if (!str_contains($downloadSource->file_path, '://')) {
                $baseUrl = config('app.url');
                $relativePath = ltrim($downloadSource->file_path, '/');
                $fullUrl = $this->normalizeRemoteUrl(rtrim($baseUrl, '/') . '/' . $relativePath);
                
                return $this->streamRemoteUrl($fullUrl, $downloadSource);
            }
            
            return response()->json([
                'error' => 'File not found on server',
                'message' => 'The download file could not be located. File path: ' . $downloadSource->file_path,
            ], 404);
        }

        // Determine filename
        $filename = basename($downloadSource->file_path);
        if ($downloadSource->label) {
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $filename = $downloadSource->label . '.' . $extension;
        }

        // Clean filename for download
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        
        // Add naraboxtv.com branding before the extension
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $nameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);
        $filename = $nameWithoutExt . ' - naraboxtv.com.' . $extension;

        return response()->download($filePath, $filename, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Stream a remote URL to the client with Content-Disposition: attachment so the browser downloads instead of following redirect (which would play, not download).
     */
    private function streamRemoteUrl(string $url, DownloadSource $downloadSource)
    {
        $url = $this->normalizeRemoteUrl($url);
        $extension = $downloadSource->format ?: pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'mp4';
        $baseName = $downloadSource->label ?: 'download';
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $baseName) . ' - naraboxtv.com.' . $extension;
        $isCdnUrl = $this->isCdnUrl($url);

        $attempts = $isCdnUrl
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
            $actualMessage = $lastError ? trim($lastError->getMessage()) : '';
            return response()->json([
                'error' => $actualMessage !== '' ? $actualMessage : 'Download source is temporarily unavailable.',
                'message' => $actualMessage !== '' ? $actualMessage : 'Download source is temporarily unavailable.',
                'source_url' => $url,
            ], 502);
        }

        $body = $response->getBody();
        $contentType = $response->getHeaderLine('Content-Type') ?: 'application/octet-stream';

        return response()->streamDownload(function () use ($body) {
            while (!$body->eof()) {
                echo $body->read(65536);
                if (ob_get_level()) {
                    ob_flush();
                }
                flush();
            }
        }, $filename, [
            'Content-Type'        => $contentType,
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function normalizeRemoteUrl(string $url): string
    {
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return $url;
        }

        $scheme = $parts['scheme'] ?? null;
        $host = $parts['host'] ?? null;
        if (!is_string($scheme) || !is_string($host)) {
            return $url;
        }

        $normalizedPath = preg_replace('#/+#', '/', (string) ($parts['path'] ?? '/')) ?: '/';
        $normalizedPath = '/' . ltrim($normalizedPath, '/');

        $rebuilt = $scheme . '://';
        if (isset($parts['user'])) {
            $rebuilt .= $parts['user'];
            if (isset($parts['pass'])) {
                $rebuilt .= ':' . $parts['pass'];
            }
            $rebuilt .= '@';
        }

        $rebuilt .= $host;

        if (isset($parts['port'])) {
            $rebuilt .= ':' . $parts['port'];
        }

        $rebuilt .= $normalizedPath;

        if (isset($parts['query'])) {
            $rebuilt .= '?' . $parts['query'];
        }

        if (isset($parts['fragment'])) {
            $rebuilt .= '#' . $parts['fragment'];
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
