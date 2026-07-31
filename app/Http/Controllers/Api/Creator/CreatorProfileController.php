<?php

namespace App\Http\Controllers\Api\Creator;

use App\Models\CreatorProfileChangeRequest;
use App\Models\MediaLibrary;
use App\Models\VJ;
use App\Services\CreatorAccessService;
use App\Services\CreatorAuditService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CreatorProfileController extends CreatorBaseController
{
    public function __construct(
        private readonly CreatorAccessService $access,
        private readonly CreatorAuditService $audit
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        if (! $this->access->workspace($request->user())) {
            return $this->notCreator();
        }

        $profiles = $this->profiles($request);

        return response()->json([
            'success' => true,
            'data' => [
                'profiles' => $profiles->map(fn (Model $profile) => $this->formatProfile($profile)),
                'pending_changes' => CreatorProfileChangeRequest::where('user_id', $request->user()->id)
                    ->whereIn('status', ['submitted', 'under_review'])
                    ->latest()
                    ->get()
                    ->map(fn ($change) => [
                        'id' => $change->id,
                        'profile_type' => class_basename($change->profile_type),
                        'profile_id' => $change->profile_id,
                        'requested_changes' => $change->requested_changes,
                        'status' => $change->status,
                        'created_at' => $change->created_at?->toIso8601String(),
                    ]),
            ],
        ]);
    }

    public function update(Request $request, string $type, int $id): JsonResponse
    {
        $profile = $this->ownedProfile($request, $type, $id);
        $validated = $request->validate([
            'display_name' => ['nullable', 'string', 'min:2', 'max:150'],
            'bio' => ['nullable', 'string', 'max:3000'],
            'location' => ['nullable', 'string', 'max:150'],
            'languages' => ['nullable', 'array', 'max:20'],
            'languages.*' => ['string', 'max:80'],
            'public_email' => ['nullable', 'email:rfc', 'max:255'],
            'official_links' => ['nullable', 'array', 'max:12'],
            'official_links.*' => ['nullable', 'url', 'max:2048'],
            'profile_image' => ['nullable', 'image', 'max:5120'],
            'cover_image' => ['nullable', 'image', 'max:10240'],
        ]);

        $direct = collect($validated)->only([
            'bio', 'location', 'languages', 'public_email', 'official_links',
        ])->all();
        if ($request->hasFile('profile_image')) {
            $direct['image'] = $request->file('profile_image')->store('creator-profiles', 'public');
        }
        if ($request->hasFile('cover_image')) {
            $direct['banner'] = $request->file('cover_image')->store('creator-covers', 'public');
        }
        if ($direct !== []) {
            $old = $profile->only(array_keys($direct));
            $profile->fill($direct)->save();
            $this->audit->record('creator.profile_updated', $request->user(), $request->user(), $profile, $old, $direct);
        }

        $requestedName = trim((string) ($validated['display_name'] ?? ''));
        $change = null;
        if ($requestedName !== '' && $requestedName !== $profile->name) {
            CreatorProfileChangeRequest::where('user_id', $request->user()->id)
                ->where('profile_type', $profile::class)
                ->where('profile_id', $profile->id)
                ->whereIn('status', ['submitted', 'under_review'])
                ->update(['status' => 'superseded']);
            $change = CreatorProfileChangeRequest::create([
                'user_id' => $request->user()->id,
                'profile_type' => $profile::class,
                'profile_id' => $profile->id,
                'requested_changes' => ['name' => $requestedName],
                'status' => 'submitted',
                'creator_message' => 'Public display-name change submitted for identity review.',
            ]);
            $this->audit->record('creator.profile_sensitive_change_requested', $request->user(), $request->user(), $change);
        }

        return response()->json([
            'success' => true,
            'message' => $change
                ? 'Profile saved. The display-name change is awaiting administrator review.'
                : 'Creator profile saved.',
            'data' => [
                'profile' => $this->formatProfile($profile->refresh()),
                'pending_change_id' => $change?->id,
            ],
        ]);
    }

    private function profiles(Request $request)
    {
        return collect([$request->user()->vjProfile, $request->user()->mediaLibraryProfile])->filter()->values();
    }

    private function ownedProfile(Request $request, string $type, int $id): Model
    {
        $class = $type === 'vj' ? VJ::class : ($type === 'media-library' ? MediaLibrary::class : null);
        abort_unless($class, 404);

        return $class::where('user_id', $request->user()->id)->findOrFail($id);
    }

    private function formatProfile(Model $profile): array
    {
        return [
            'type' => $profile instanceof VJ ? 'vj' : 'media_library',
            'id' => $profile->id,
            'name' => $profile->name,
            'slug' => $profile->slug,
            'bio' => $profile->bio,
            'image' => $profile->image ? $this->resolveMediaUrl($profile->image) : null,
            'banner' => $profile->banner ? $this->resolveMediaUrl($profile->banner) : null,
            'languages' => $profile->languages ?? [],
            'location' => $profile->location,
            'official_links' => $profile->official_links ?? [],
            'public_email' => $profile->public_email,
            'verified' => (bool) $profile->is_verified,
            'profile_review_status' => $profile->profile_review_status,
        ];
    }
}
