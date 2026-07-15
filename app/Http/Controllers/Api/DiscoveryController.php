<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Movie;
use App\Models\TVShow;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DiscoveryController extends Controller
{
    private function imageUrl(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return asset('storage/' . ltrim($path, '/'));
    }

    private function formatReleaseYearItem(object $item): array
    {
        return [
            'id' => $item->id,
            'title' => $item->title,
            'thumbnail' => $this->imageUrl($item->thumbnail ?: $item->backdrop),
            'release_date' => $item->release_date,
        ];
    }

    public function releaseYears()
    {
        $movieYears = Movie::query()
            ->whereNotNull('release_date')
            ->where('is_active', true)
            ->publiclyVisible()
            ->selectRaw('YEAR(release_date) as year')
            ->pluck('year');

        $showYears = TVShow::query()
            ->whereNotNull('release_date')
            ->where('is_active', true)
            ->publiclyVisible()
            ->selectRaw('YEAR(release_date) as year')
            ->pluck('year');

        return response()->json([
            'data' => $movieYears
                ->merge($showYears)
                ->filter()
                ->unique()
                ->sortDesc()
                ->values(),
        ]);
    }

    public function byReleaseYear(Request $request)
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:1900|max:2100',
        ]);

        $movies = Movie::query()
            ->whereYear('release_date', $validated['year'])
            ->where('is_active', true)
            ->publiclyVisible()
            ->orderByDesc('release_date')
            ->get(['id', 'title', 'thumbnail', 'backdrop', 'release_date'])
            ->map(fn ($item) => $this->formatReleaseYearItem($item));

        $shows = TVShow::query()
            ->whereYear('release_date', $validated['year'])
            ->where('is_active', true)
            ->publiclyVisible()
            ->orderByDesc('release_date')
            ->get(['id', 'title', 'thumbnail', 'backdrop', 'release_date'])
            ->map(fn ($item) => $this->formatReleaseYearItem($item));

        return response()->json([
            'data' => [
                'year' => $validated['year'],
                'movies' => $movies,
                'tv_shows' => $shows,
            ],
        ]);
    }

    public function latestByReleaseDate()
    {
        return response()->json([
            'data' => [
                'movies' => Movie::query()
                    ->where('is_active', true)
                    ->whereNotNull('release_date')
                    ->publiclyVisible()
                    ->orderByDesc('release_date')
                    ->limit(20)
                    ->get(['id', 'title', 'thumbnail', 'backdrop', 'release_date'])
                    ->map(fn ($item) => $this->formatReleaseYearItem($item)),
                'tv_shows' => TVShow::query()
                    ->where('is_active', true)
                    ->whereNotNull('release_date')
                    ->publiclyVisible()
                    ->orderByDesc('release_date')
                    ->limit(20)
                    ->get(['id', 'title', 'thumbnail', 'backdrop', 'release_date'])
                    ->map(fn ($item) => $this->formatReleaseYearItem($item)),
            ],
        ]);
    }
}
