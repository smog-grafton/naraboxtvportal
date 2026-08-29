<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PlatformOperationsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BootstrapController extends Controller
{
    public function __invoke(Request $request, PlatformOperationsService $operations): JsonResponse
    {
        $validated = $request->validate([
            'platform' => ['nullable', 'string', 'max:32'],
            'version' => ['nullable', 'string', 'max:32'],
            'build' => ['nullable', 'integer', 'min:0'],
        ]);

        $platform = $request->header('X-NaraBox-Platform', $validated['platform'] ?? null);
        $version = $request->header('X-NaraBox-Version', $validated['version'] ?? null);
        $build = (int) $request->header('X-NaraBox-Build', (string) ($validated['build'] ?? 0));
        $payload = $operations->bootstrap((string) $platform, $build, $version);
        $etag = '"'.sha1(json_encode([$payload['revision'], $payload['platform'], $payload['version_policy'], $payload['maintenance']])).'"';

        if ($request->header('If-None-Match') === $etag) {
            return response()->json(null, 304)->header('ETag', $etag)->header('Cache-Control', 'private, max-age=30');
        }

        return response()->json(['data' => $payload])
            ->header('ETag', $etag)
            ->header('Cache-Control', 'private, max-age=30, stale-if-error=300');
    }
}
