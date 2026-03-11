<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VideoSource;
use App\Services\VideoSourceDerivationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WorkerSyncController extends Controller
{
    public function sync(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'hint' => ['nullable', 'string', 'max:512'],
            'cdn_asset_id' => ['nullable', 'string', 'max:64'],
            'cdn_source_id' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'string', 'in:completed,failed'],
            'playback_ready' => ['nullable', 'boolean'],
        ]);

        Log::info('Portal worker sync received', [
            'hint' => $validated['hint'] ?? null,
            'cdn_asset_id' => $validated['cdn_asset_id'] ?? null,
            'cdn_source_id' => $validated['cdn_source_id'] ?? null,
        ]);

        $refreshed = 0;
        if (! empty($validated['cdn_asset_id']) && ! empty($validated['cdn_source_id'])) {
            $sources = VideoSource::where('metadata->cdn_asset_id', $validated['cdn_asset_id'])
                ->where('metadata->cdn_source_id', (int) $validated['cdn_source_id'])
                ->get();
            foreach ($sources as $videoSource) {
                app(VideoSourceDerivationService::class)->ensureDerivedSourcesForCdnUrl($videoSource);
                $refreshed++;
            }
        }

        return response()->json([
            'success' => true,
            'refreshed_sources' => $refreshed,
        ]);
    }
}
