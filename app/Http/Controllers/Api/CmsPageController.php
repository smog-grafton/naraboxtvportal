<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CmsPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CmsPageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = CmsPage::query()->published()->orderBy('sort_order')->orderBy('title');

        $data = $query->get(['slug', 'title', 'updated_at']);

        return response()->json([
            'data' => $data,
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $page = CmsPage::query()
            ->published()
            ->where('slug', $slug)
            ->first();

        if (! $page) {
            return response()->json(['message' => 'Page not found'], 404);
        }

        return response()->json([
            'data' => [
                'slug' => $page->slug,
                'title' => $page->title,
                'body' => $page->body,
                'updated_at' => $page->updated_at?->toIso8601String(),
            ],
        ]);
    }
}
