<?php

namespace App\Http\Controllers\Api\Creator;

use App\Models\Episode;
use App\Models\Movie;
use App\Models\Subtitle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CreatorSubtitleController extends CreatorBaseController
{
    public function indexForMovie(Request $request, int $id): JsonResponse
    {
        return $this->index($this->creatorMovieQuery($request->user())->findOrFail($id));
    }

    public function storeForMovie(Request $request, int $id): JsonResponse
    {
        return $this->store($request, $this->creatorMovieQuery($request->user())->findOrFail($id));
    }

    public function indexForEpisode(Request $request, int $id): JsonResponse
    {
        return $this->index($this->ownedEpisode($request, $id));
    }

    public function storeForEpisode(Request $request, int $id): JsonResponse
    {
        return $this->store($request, $this->ownedEpisode($request, $id));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $subtitle = Subtitle::findOrFail($id);
        $owner = $subtitle->subtitleable;
        abort_unless($owner instanceof Movie || $owner instanceof Episode, 404);
        if ($owner instanceof Movie) {
            $this->creatorMovieQuery($request->user())->findOrFail($owner->id);
        } else {
            $this->ownedEpisode($request, $owner->id);
        }

        if ($subtitle->file_path) {
            Storage::disk('public')->delete($subtitle->file_path);
        }
        $subtitle->delete();

        return response()->json(['success' => true, 'message' => 'Subtitle removed.']);
    }

    private function index(Model $content): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $content->subtitles()->orderBy('sort_order')->get()->map(fn (Subtitle $subtitle) => $this->format($subtitle)),
        ]);
    }

    private function store(Request $request, Model $content): JsonResponse
    {
        $extensions = config('creator.subtitle_extensions', []);
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:'.implode(',', $extensions)],
            'language' => ['required', 'string', 'max:10'],
            'label' => ['nullable', 'string', 'max:100'],
            'format' => ['required', Rule::in($extensions)],
            'is_default' => ['nullable', 'boolean'],
        ]);
        $extension = strtolower((string) $request->file('file')->getClientOriginalExtension());
        abort_unless($extension === strtolower($validated['format']), 422, 'Subtitle format must match the uploaded file.');
        $path = $request->file('file')->store('subtitles/creator/'.$request->user()->id, 'public');

        if ($request->boolean('is_default')) {
            $content->subtitles()->update(['is_default' => false]);
        }
        $subtitle = $content->subtitles()->create([
            'language' => $validated['language'],
            'label' => $validated['label'] ?? strtoupper($validated['language']),
            'type' => 'upload',
            'file_path' => $path,
            'format' => $validated['format'],
            'is_default' => $request->boolean('is_default'),
            'is_active' => true,
            'sort_order' => ((int) $content->subtitles()->max('sort_order')) + 1,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Subtitle uploaded.',
            'data' => $this->format($subtitle),
        ], 201);
    }

    private function ownedEpisode(Request $request, int $id): Episode
    {
        $episode = Episode::with('season')->findOrFail($id);
        abort_unless(
            $episode->season
            && $this->creatorTvShowQuery($request->user())->whereKey($episode->season->tv_show_id)->exists(),
            404
        );

        return $episode;
    }

    private function format(Subtitle $subtitle): array
    {
        return [
            'id' => $subtitle->id,
            'language' => $subtitle->language,
            'label' => $subtitle->label,
            'format' => $subtitle->format,
            'is_default' => $subtitle->is_default,
            'is_active' => $subtitle->is_active,
            'url' => $subtitle->full_url,
        ];
    }
}
