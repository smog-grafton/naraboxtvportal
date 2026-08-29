<?php

namespace App\Http\Controllers\Api\Creator;

use App\Models\Actor;
use App\Models\Category;
use App\Models\Genre;
use App\Models\SubscriptionPlan;
use App\Services\CreatorAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreatorFormOptionsController extends CreatorBaseController
{
    public function __construct(private readonly CreatorAccessService $access)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $this->access->workspace($user)) {
            return $this->notCreator();
        }

        $profiles = collect([$user->vjProfile, $user->mediaLibraryProfile])
            ->filter()
            ->map(fn ($profile) => [
                'type' => $profile instanceof \App\Models\VJ ? 'vj' : 'media_library',
                'id' => $profile->id,
                'name' => $profile->name,
                'verified' => (bool) $profile->is_verified,
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'genres' => Genre::query()->select('id', 'name', 'slug')->orderBy('name')->get(),
                'categories' => Category::query()->select('id', 'name', 'slug')->orderBy('name')->get(),
                'actors' => Actor::query()->select('id', 'name', 'image')->orderBy('name')->limit(1000)->get(),
                'subscription_plans' => SubscriptionPlan::query()
                    ->where('is_active', true)
                    ->select('id', 'name', 'slug', 'description', 'duration_days', 'price')
                    ->orderBy('sort_order')
                    ->get(),
                'managed_profiles' => $profiles,
                'countries' => config('creator.countries', []),
                'languages' => config('creator.languages', []),
                'content_ratings' => config('creator.content_ratings', []),
                'source_methods' => [
                    ['value' => 'local', 'label' => 'Upload from this device', 'enabled' => true],
                    ['value' => 'telegram', 'label' => 'Telegram post link', 'enabled' => true],
                    ['value' => 'remote', 'label' => 'Direct download URL', 'enabled' => true],
                ],
                'storage_targets' => collect(config('storage_targets.targets', []))
                    ->map(fn (array $target, string $key) => [
                        'value' => $key,
                        'label' => (string) ($target['label'] ?? $key),
                        'provider' => $target['provider'] ?? null,
                        'bucket' => $target['bucket'] ?? null,
                        'enabled' => (bool) ($target['enabled'] ?? false),
                        'writable' => (bool) ($target['writable'] ?? false),
                        'recommended' => $key === 'r2_nbx',
                    ])
                    ->prepend([
                        'value' => 'auto',
                        'label' => 'Automatic',
                        'provider' => 'auto',
                        'bucket' => null,
                        'enabled' => true,
                        'writable' => true,
                        'recommended' => true,
                    ])
                    ->values(),
                'video' => [
                    'max_upload_bytes' => (int) config('creator.max_upload_bytes'),
                    'allowed_extensions' => config('creator.allowed_video_extensions', []),
                    'allowed_mimes' => config('creator.allowed_video_mimes', []),
                    'processing_profiles' => collect(config('creator.processing_profiles', []))
                        ->map(fn (array $profile, string $key) => [
                            'value' => $key,
                            'label' => $profile['label'] ?? ucfirst($key),
                            'max_resolution' => $profile['max_resolution'] ?? null,
                            'hls' => collect(['480p', '720p', '1080p'])
                                ->filter(fn (string $resolution) => (bool) ($profile['hls_'.$resolution] ?? false))
                                ->values(),
                        ])->values(),
                ],
                'subtitles' => [
                    'allowed_extensions' => config('creator.subtitle_extensions', []),
                    'max_upload_bytes' => 10 * 1024 * 1024,
                ],
                'monetization' => [
                    'currency' => 'UGX',
                    'can_request' => $this->access->canMonetize($user),
                    'rent' => config('creator.rent'),
                    'purchase' => config('creator.purchase'),
                    'requires_admin_approval' => true,
                ],
                'permissions' => [
                    'can_create_drafts' => $this->access->canSubmitDrafts($user),
                    'can_publish' => $this->access->canPublish($user),
                    'can_monetize' => $this->access->canMonetize($user),
                    'can_withdraw' => $this->access->canWithdraw($user),
                ],
            ],
        ]);
    }
}
