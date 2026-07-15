<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CdnFetchProxyController extends Controller
{
    public function fetchAndPush(Request $request): JsonResponse
    {
        $token = (string) config('services.cdn.fetch_proxy_token', '');
        $provided = (string) $request->bearerToken();
        if ($token === '' || $provided === '' || ! hash_equals($token, $provided)) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => 'Unauthorized proxy request.',
            ], 401);
        }

        $validated = $request->validate([
            'source_id' => ['required', 'integer'],
            'asset_id' => ['required', 'uuid'],
            'url' => ['required', 'url', 'max:2048'],
            'filename' => ['nullable', 'string', 'max:255'],
            'mime_type' => ['nullable', 'string', 'max:100'],
            'size_bytes' => ['nullable', 'integer', 'min:0'],
        ]);

        if (! $this->isAllowedRemoteUrl((string) $validated['url'])) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => 'Blocked remote URL for security reasons.',
            ], 422);
        }

        \App\Jobs\CdnFetchAndPushJob::dispatch(
            (int) $validated['source_id'],
            (string) $validated['asset_id'],
            (string) $validated['url'],
            isset($validated['filename']) ? (string) $validated['filename'] : null,
            isset($validated['mime_type']) ? (string) $validated['mime_type'] : null,
            isset($validated['size_bytes']) ? (int) $validated['size_bytes'] : null
        )->onQueue('cdn-proxy');

        return response()->json([
            'success' => true,
            'data' => [
                'queued' => true,
            ],
            'error' => null,
        ], 202);
    }

    private function isAllowedRemoteUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (! is_array($parts)) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (! in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '' || in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return ! $this->isPrivateOrReservedIp($host);
        }

        $resolved = gethostbynamel($host);
        if (! is_array($resolved) || $resolved === []) {
            return false;
        }

        foreach ($resolved as $ip) {
            if ($this->isPrivateOrReservedIp($ip)) {
                return false;
            }
        }

        return true;
    }

    private function isPrivateOrReservedIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }
}
