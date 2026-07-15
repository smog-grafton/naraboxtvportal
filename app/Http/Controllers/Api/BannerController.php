<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdBanner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * @group Ad banners
 *
 * Read-only banner API for web/app clients.
 */
class BannerController extends Controller
{
    /**
     * List active banners
     *
     * Filter banners by placement and platform (app/web/all).
     */
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'placement' => 'sometimes|string|max:255',
            'platform' => 'sometimes|string|in:app,web,all',
            'limit' => 'sometimes|integer|min:1|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors(),
            ], 422);
        }

        $query = AdBanner::active()->orderBy('sort_order')->orderBy('id');

        if ($request->filled('placement')) {
            $query->where('placement', $request->input('placement'));
        }

        if ($request->filled('platform') && $request->input('platform') !== 'all') {
            $platform = $request->input('platform');
            $query->where(function ($q) use ($platform) {
                $q->where('platform', 'all')
                    ->orWhere('platform', $platform);
            });
        }

        $limit = (int) $request->input('limit', 20);

        $banners = $query->limit($limit)->get()->map(function (AdBanner $banner) {
            return [
                'id' => $banner->id,
                'name' => $banner->name,
                'slug' => $banner->slug,
                'type' => $banner->type,
                'image_url' => $banner->type === 'image' ? $banner->image_path : null,
                'script_content' => $banner->type === 'script' ? $banner->script_content : null,
                'target_url' => $banner->target_url,
                'width' => $banner->width,
                'height' => $banner->height,
                'placement' => $banner->placement,
                'platform' => $banner->platform,
            ];
        });

        return response()->json([
            'data' => $banners,
        ]);
    }
}

