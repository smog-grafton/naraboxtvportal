<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MediaLibrary;
use Illuminate\Http\Request;

/**
 * @group Media Libraries
 *
 * List and fetch media library creators (channels/producers).
 */
class MediaLibraryController extends Controller
{
    /**
     * List media libraries. Query: featured (1), limit.
     */
    public function index(Request $request)
    {
        $query = MediaLibrary::where('is_active', true);

        if ($request->boolean('featured')) {
            $query->where('is_featured', true)
                  ->orderBy('featured_order');
        } else {
            $query->orderBy('name');
        }

        $limit = $request->get('limit');
        $libraries = $limit ? $query->limit($limit)->get() : $query->get();

        $libraries = $libraries->map(function ($lib) {
            return $this->formatLibrary($lib);
        });

        return response()->json(['data' => $libraries]);
    }

    /**
     * Show a single media library by ID or slug.
     */
    public function show($id)
    {
        $library = MediaLibrary::where('is_active', true)
            ->with(['movies' => fn ($q) => $q->where('is_active', true)->with(['genres', 'category'])->limit(100)],
                   ['tvShows' => fn ($q) => $q->where('is_active', true)->with(['genres', 'category'])->limit(100)])
            ->where(function ($q) use ($id) {
                $q->where('id', $id)->orWhere('slug', $id);
            })
            ->first();

        if (!$library) {
            return response()->json(['message' => 'Media library not found'], 404);
        }

        $data = $this->formatLibrary($library);
        $data['movies'] = $library->movies->map(fn ($m) => [
            'id' => $m->id,
            'slug' => $m->slug,
            'title' => $m->title,
            'thumbnail' => $this->assetUrl($m->thumbnail),
            'rating' => (float) $m->rating,
            'genre' => $m->genres->pluck('name')->toArray(),
        ]);
        $data['tvShows'] = $library->tvShows->map(fn ($t) => [
            'id' => $t->id,
            'slug' => $t->slug,
            'title' => $t->title,
            'thumbnail' => $this->assetUrl($t->thumbnail),
            'rating' => (float) $t->rating,
            'genre' => $t->genres->pluck('name')->toArray(),
        ]);
        $data['moviesCount'] = $library->movies()->where('is_active', true)->count();
        $data['tvShowsCount'] = $library->tvShows()->where('is_active', true)->count();

        return response()->json($data);
    }

    private function formatLibrary(MediaLibrary $lib): array
    {
        return [
            'id' => $lib->id,
            'name' => $lib->name,
            'slug' => $lib->slug,
            'image' => $this->assetUrl($lib->image),
            'banner' => $this->assetUrl($lib->banner),
            'bio' => $lib->bio,
            'isVerified' => (bool) $lib->is_verified,
        ];
    }

    private function assetUrl(?string $path): ?string
    {
        if (empty($path)) return null;
        if (str_starts_with($path, 'http')) return $path;
        return asset('storage/' . $path);
    }
}
