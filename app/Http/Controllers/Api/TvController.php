<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\HeroSlide;
use App\Models\LiveStream;
use App\Models\Movie;
use App\Models\PaymentTransaction;
use App\Models\SubscriptionPlan;
use App\Models\TvCheckoutSession;
use App\Models\TvDevice;
use App\Models\TvDeviceCode;
use App\Models\TVShow;
use App\Models\User;
use App\Models\UserPurchase;
use App\Models\UserRental;
use App\Models\UserSubscription;
use App\Models\WatchHistory;
use App\Services\PaymentApprovalService;
use App\Support\EditorialArticlePresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TvController extends Controller
{
    public function __construct(
        private readonly EditorialArticlePresenter $articlePresenter,
    ) {
    }

    public function issueDeviceCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => 'nullable|string|max:255',
            'device_name' => 'nullable|string|max:255',
            'platform' => 'nullable|string|max:64',
            'app_version' => 'nullable|string|max:64',
        ]);

        $device = $this->resolveDeviceFromPayload($request, $validated, null);
        $expiresAt = now()->addMinutes(10);
        $plainDeviceCode = Str::random(80);

        $deviceCode = TvDeviceCode::create([
            'tv_device_id' => $device?->id,
            'user_code' => $this->generateUniqueUserCode(),
            'device_code_hash' => hash('sha256', $plainDeviceCode),
            'status' => TvDeviceCode::STATUS_PENDING,
            'expires_at' => $expiresAt,
            'issued_ip' => $request->ip(),
            'issued_user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
        ]);

        $verificationPath = '/tv/activate?code=' . urlencode($deviceCode->user_code);
        $pairingPath = '/tv/pair?code=' . urlencode($deviceCode->user_code);
        $verificationUri = rtrim($this->frontendUrl(), '/') . $verificationPath;
        $pairingUri = rtrim($this->frontendUrl(), '/') . $pairingPath;

        return response()->json([
            'data' => [
                'device_code' => $plainDeviceCode,
                'user_code' => $deviceCode->user_code,
                'verification_uri' => $verificationUri,
                'verification_path' => $verificationPath,
                'pairing_uri' => $pairingUri,
                'pairing_path' => $pairingPath,
                'qr_payload' => $pairingUri,
                'expires_at' => $expiresAt->toIso8601String(),
                'interval' => 5,
            ],
        ]);
    }

    public function pollDeviceCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_code' => 'required|string|min:40|max:255',
        ]);

        $record = TvDeviceCode::query()
            ->where('device_code_hash', hash('sha256', $validated['device_code']))
            ->first();

        if (! $record) {
            return response()->json([
                'error' => 'Device code is invalid.',
                'data' => ['status' => 'INVALID'],
            ], 404);
        }

        if ($record->expires_at->isPast() && $record->status !== TvDeviceCode::STATUS_CONSUMED) {
            $record->update(['status' => TvDeviceCode::STATUS_EXPIRED]);
        }

        $record->forceFill(['last_polled_at' => now()])->save();

        if ($record->status === TvDeviceCode::STATUS_PENDING) {
            return response()->json([
                'data' => [
                    'status' => TvDeviceCode::STATUS_PENDING,
                    'expires_at' => $record->expires_at?->toIso8601String(),
                ],
            ]);
        }

        if ($record->status === TvDeviceCode::STATUS_EXPIRED) {
            return response()->json([
                'data' => [
                    'status' => TvDeviceCode::STATUS_EXPIRED,
                    'expires_at' => $record->expires_at?->toIso8601String(),
                ],
            ], 410);
        }

        if ($record->status === TvDeviceCode::STATUS_CONSUMED) {
            return response()->json([
                'data' => [
                    'status' => TvDeviceCode::STATUS_CONSUMED,
                ],
            ]);
        }

        $user = $record->user;
        if (! $user) {
            return response()->json([
                'error' => 'The linked account no longer exists.',
                'data' => ['status' => 'INVALID'],
            ], 404);
        }

        $token = $user->createToken('tv_device_' . ($record->device?->device_identifier ?? Str::random(8)))->plainTextToken;

        $record->update([
            'status' => TvDeviceCode::STATUS_CONSUMED,
            'consumed_at' => now(),
        ]);

        if ($record->device) {
            $record->device->update([
                'user_id' => $user->id,
                'activated_at' => $record->device->activated_at ?? now(),
                'last_seen_at' => now(),
                'last_ip' => request()->ip(),
                'last_user_agent' => Str::limit((string) request()->userAgent(), 1000, ''),
            ]);
        }

        return response()->json([
            'data' => [
                'status' => TvDeviceCode::STATUS_APPROVED,
                'token' => $token,
                'user' => $this->formatUser($user),
                'device' => $this->formatDevice($record->device),
            ],
        ]);
    }

    public function activateDeviceCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_code' => 'required|string|max:12',
        ]);

        $user = $request->user();
        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $userCode = strtoupper(trim($validated['user_code']));

        $record = TvDeviceCode::query()
            ->where('user_code', $userCode)
            ->where('status', TvDeviceCode::STATUS_PENDING)
            ->where('expires_at', '>', now())
            ->first();

        if (! $record) {
            return response()->json([
                'error' => 'Activation code is invalid or expired.',
            ], 422);
        }

        $record->update([
            'user_id' => $user->id,
            'status' => TvDeviceCode::STATUS_APPROVED,
            'approved_at' => now(),
            'approved_ip' => $request->ip(),
            'approved_user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
        ]);

        if ($record->device) {
            $record->device->update([
                'user_id' => $user->id,
                'activated_at' => $record->device->activated_at ?? now(),
                'last_seen_at' => now(),
            ]);
        }

        return response()->json([
            'message' => 'TV device approved successfully.',
            'data' => [
                'status' => TvDeviceCode::STATUS_APPROVED,
                'user_code' => $record->user_code,
                'device' => $this->formatDevice($record->device),
            ],
        ]);
    }

    public function home(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = Auth::guard('sanctum')->user();

        $this->touchExistingDevice($request, $user);

        $hero = HeroSlide::query()
            ->where('is_active', true)
            ->with(['media.genres', 'media.vj', 'media.mediaLibrary'])
            ->orderBy('order')
            ->limit(8)
            ->get()
            ->map(fn (HeroSlide $slide) => $slide->media)
            ->filter(fn ($media) => $media && $media->is_active)
            ->values()
            ->map(fn ($movie) => $this->formatMovieCard($movie, true))
            ->all();

        $rails = [];

        if ($user) {
            $continueWatching = WatchHistory::query()
                ->where('user_id', $user->id)
                ->with(['media', 'tvShow', 'episode.season.tvShow'])
                ->orderByDesc('last_watched_at')
                ->limit(15)
                ->get()
                ->map(fn (WatchHistory $history) => $this->formatWatchHistoryCard($history))
                ->filter()
                ->values()
                ->all();

            if ($continueWatching !== []) {
                $rails[] = [
                    'id' => 'continue-watching',
                    'title' => 'Continue Watching',
                    'layout' => 'landscape',
                    'items' => $continueWatching,
                ];
            }
        }

        $liveNow = LiveStream::query()
            ->where('is_active', true)
            ->where('is_live', true)
            ->orderBy('order')
            ->limit(10)
            ->get()
            ->map(fn (LiveStream $stream) => $this->formatLiveStreamCard($stream))
            ->all();

        if ($liveNow !== []) {
            $rails[] = [
                'id' => 'live-now',
                'title' => 'Live Now',
                'layout' => 'landscape',
                'items' => $liveNow,
            ];
        }

        $rails[] = [
            'id' => 'trending-movies',
            'title' => 'Trending Movies',
            'layout' => 'poster',
            'items' => Movie::query()
                ->where('is_active', true)
                ->where(function ($query) {
                    $query->whereNull('content_status')
                        ->orWhere('content_status', 'published');
                })
                ->with(['genres', 'vj', 'mediaLibrary'])
                ->orderByDesc('trending_score')
                ->orderByRaw('(views_count + manual_views) DESC')
                ->limit(16)
                ->get()
                ->map(fn (Movie $movie) => $this->formatMovieCard($movie))
                ->all(),
        ];

        $rails[] = [
            'id' => 'series-spotlight',
            'title' => 'Series Spotlight',
            'layout' => 'poster',
            'items' => TVShow::query()
                ->where('is_active', true)
                ->where(function ($query) {
                    $query->whereNull('content_status')
                        ->orWhere('content_status', 'published');
                })
                ->with(['genres', 'vj', 'mediaLibrary'])
                ->orderByDesc('trending_score')
                ->orderByRaw('(views_count + manual_views) DESC')
                ->limit(16)
                ->get()
                ->map(fn (TVShow $show) => $this->formatTvShowCard($show))
                ->all(),
        ];

        $rails[] = [
            'id' => 'vj-dub-highlights',
            'title' => 'Translated by VJs',
            'layout' => 'poster',
            'items' => Movie::query()
                ->where('is_active', true)
                ->whereHas('vj')
                ->with(['genres', 'vj', 'mediaLibrary'])
                ->orderByDesc('trending_score')
                ->limit(16)
                ->get()
                ->map(fn (Movie $movie) => $this->formatMovieCard($movie))
                ->all(),
        ];

        $rails[] = [
            'id' => 'free-to-watch',
            'title' => 'Free To Watch',
            'layout' => 'poster',
            'items' => collect(Movie::query()
                ->where('is_active', true)
                ->where('is_free', true)
                ->with(['genres', 'vj', 'mediaLibrary'])
                ->orderByDesc('trending_score')
                ->limit(8)
                ->get()
                ->map(fn (Movie $movie) => $this->formatMovieCard($movie)))
                ->merge(TVShow::query()
                    ->where('is_active', true)
                    ->where('is_free', true)
                    ->with(['genres', 'vj', 'mediaLibrary'])
                    ->orderByDesc('trending_score')
                    ->limit(8)
                    ->get()
                    ->map(fn (TVShow $show) => $this->formatTvShowCard($show)))
                ->take(16)
                ->values()
                ->all(),
        ];

        $rails[] = [
            'id' => 'editorial-picks',
            'title' => 'Editorial Picks',
            'layout' => 'landscape',
            'items' => Article::query()
                ->where('is_published', true)
                ->latest('date')
                ->limit(12)
                ->get()
                ->map(fn (Article $article) => $this->formatArticleCard($article))
                ->all(),
        ];

        return response()->json([
            'data' => [
                'meta' => [
                    'authenticated' => (bool) $user,
                    'user' => $user ? $this->formatUser($user) : null,
                    'generated_at' => now()->toIso8601String(),
                ],
                'hero' => $hero,
                'rails' => array_values(array_filter($rails, fn (array $rail) => ! empty($rail['items']))),
                'subscription_plans' => SubscriptionPlan::query()
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->get()
                    ->map(fn (SubscriptionPlan $plan) => [
                        'id' => $plan->id,
                        'name' => $plan->name,
                        'slug' => $plan->slug,
                        'description' => $plan->description,
                        'duration_days' => $plan->duration_days,
                        'price' => (float) $plan->price,
                    ])->all(),
            ],
        ]);
    }

    public function createCheckoutSession(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'type' => 'required|in:RENT,BUY,SUBSCRIPTION',
            'media_id' => 'required_if:type,RENT,BUY|integer',
            'media_type' => 'required_if:type,RENT,BUY|in:MOVIE,TV_SHOW',
            'subscription_plan_id' => 'required_if:type,SUBSCRIPTION|exists:subscription_plans,id',
            'device_id' => 'nullable|string|max:255',
            'device_name' => 'nullable|string|max:255',
            'platform' => 'nullable|string|max:64',
            'app_version' => 'nullable|string|max:64',
        ]);

        [$title, $amount] = $this->resolveCheckoutTarget($validated);
        $device = $this->resolveDeviceFromPayload($request, $validated, $user);

        $session = TvCheckoutSession::create([
            'user_id' => $user->id,
            'tv_device_id' => $device?->id,
            'uuid' => (string) Str::ulid(),
            'status' => TvCheckoutSession::STATUS_PENDING,
            'type' => $validated['type'],
            'media_type' => $validated['media_type'] ?? null,
            'media_id' => $validated['media_id'] ?? null,
            'subscription_plan_id' => $validated['subscription_plan_id'] ?? null,
            'title' => $title,
            'amount' => $amount,
            'expires_at' => now()->addMinutes(30),
            'meta' => [
                'origin' => 'tv_app',
            ],
        ]);

        return response()->json([
            'data' => $this->formatCheckoutSession($session, true),
        ]);
    }

    public function showCheckoutSession(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $session = TvCheckoutSession::query()
            ->with(['subscriptionPlan', 'device'])
            ->where('uuid', $uuid)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $session->update(['last_viewed_at' => now()]);
        $this->syncCheckoutSessionStatus($session);

        return response()->json([
            'data' => $this->formatCheckoutSession($session->fresh(['subscriptionPlan', 'device']), true),
        ]);
    }

    public function attachCheckoutTransaction(Request $request, string $uuid): JsonResponse
    {
        $validated = $request->validate([
            'transaction_ref' => 'required|string|max:255',
        ]);

        $user = $request->user();
        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $session = TvCheckoutSession::query()
            ->with(['subscriptionPlan', 'device'])
            ->where('uuid', $uuid)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $transaction = PaymentTransaction::query()
            ->where('user_id', $user->id)
            ->where('transaction_ref', $validated['transaction_ref'])
            ->firstOrFail();

        if ($transaction->type !== $session->type) {
            return response()->json([
                'error' => 'This payment does not match the checkout session.',
            ], 422);
        }

        $session->update([
            'transaction_ref' => $transaction->transaction_ref,
            'amount' => $transaction->amount,
            'status' => $transaction->status === 'SUCCESS'
                ? TvCheckoutSession::STATUS_COMPLETED
                : ($transaction->status === 'FAILED' ? TvCheckoutSession::STATUS_FAILED : TvCheckoutSession::STATUS_PENDING_PAYMENT),
        ]);

        $this->syncCheckoutSessionStatus($session);

        return response()->json([
            'data' => $this->formatCheckoutSession($session->fresh(['subscriptionPlan', 'device']), true),
        ]);
    }

    private function resolveDeviceFromPayload(Request $request, array $payload, ?User $user): ?TvDevice
    {
        $deviceIdentifier = trim((string) ($payload['device_id'] ?? $request->header('X-TV-Device-ID') ?? ''));
        if ($deviceIdentifier === '') {
            return null;
        }

        return TvDevice::updateOrCreate(
            ['device_identifier' => $deviceIdentifier],
            [
                'user_id' => $user?->id,
                'name' => $payload['device_name'] ?? null,
                'platform' => $payload['platform'] ?? null,
                'app_version' => $payload['app_version'] ?? null,
                'last_seen_at' => now(),
                'last_ip' => $request->ip(),
                'last_user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
            ]
        );
    }

    private function touchExistingDevice(Request $request, ?User $user): void
    {
        $deviceIdentifier = trim((string) ($request->header('X-TV-Device-ID') ?? $request->query('device_id', '')));
        if ($deviceIdentifier === '') {
            return;
        }

        $device = TvDevice::query()->where('device_identifier', $deviceIdentifier)->first();
        if (! $device) {
            return;
        }

        $device->update([
            'user_id' => $user?->id ?? $device->user_id,
            'last_seen_at' => now(),
            'last_ip' => $request->ip(),
            'last_user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
        ]);
    }

    private function generateUniqueUserCode(): string
    {
        do {
            $code = Str::upper(Str::random(6));
        } while (TvDeviceCode::query()->where('user_code', $code)->exists());

        return $code;
    }

    private function resolveCheckoutTarget(array $validated): array
    {
        if ($validated['type'] === 'SUBSCRIPTION') {
            $plan = SubscriptionPlan::query()->findOrFail($validated['subscription_plan_id']);

            return [$plan->name . ' Subscription', (float) $plan->price];
        }

        $model = $validated['media_type'] === 'MOVIE'
            ? Movie::query()->findOrFail($validated['media_id'])
            : TVShow::query()->findOrFail($validated['media_id']);

        $amount = $validated['type'] === 'RENT'
            ? (float) ($model->price_rent ?? 0)
            : (float) ($model->price_buy ?? 0);

        if ($amount <= 0) {
            abort(422, 'This content does not support the selected payment action.');
        }

        return [$model->title, $amount];
    }

    private function syncCheckoutSessionStatus(TvCheckoutSession $session): void
    {
        $user = $session->user()->first();
        if (! $user) {
            return;
        }

        $status = $session->status;
        $accessGranted = $this->checkoutSessionHasAccess($session, $user);
        $transaction = null;

        if ($accessGranted) {
            $status = TvCheckoutSession::STATUS_COMPLETED;
        }

        if ($session->transaction_ref) {
            $transaction = PaymentTransaction::query()
                ->with(['payment', 'paymentGateway', 'transactionable', 'subscriptionPlan', 'user'])
                ->where('user_id', $user->id)
                ->where('transaction_ref', $session->transaction_ref)
                ->first();

            if ($transaction) {
                if ($transaction->status === 'SUCCESS' && ! $accessGranted) {
                    try {
                        PaymentApprovalService::approveTransaction($transaction);
                        $accessGranted = $this->checkoutSessionHasAccess($session, $user);
                    } catch (\Throwable $exception) {
                        Log::warning('tv_checkout.access_repair_failed', [
                            'session_id' => $session->id,
                            'transaction_ref' => $transaction->transaction_ref,
                            'error' => $exception->getMessage(),
                        ]);
                    }
                }

                if ($accessGranted) {
                    $status = TvCheckoutSession::STATUS_COMPLETED;
                } elseif (in_array($transaction->status, ['FAILED', 'CANCELLED', 'EXPIRED'], true)) {
                    $status = TvCheckoutSession::STATUS_FAILED;
                } elseif (in_array($transaction->status, ['PENDING', 'SUCCESS'], true)) {
                    $status = TvCheckoutSession::STATUS_PENDING_PAYMENT;
                }
            }
        }

        if (
            ! $transaction
            && ! $accessGranted
            && $session->expires_at?->isPast()
            && $session->status !== TvCheckoutSession::STATUS_COMPLETED
        ) {
            $status = TvCheckoutSession::STATUS_EXPIRED;
        }

        $updates = ['status' => $status];
        if ($status === TvCheckoutSession::STATUS_COMPLETED && ! $session->completed_at) {
            $updates['completed_at'] = now();
        }

        $session->update($updates);
    }

    private function checkoutSessionHasAccess(TvCheckoutSession $session, User $user): bool
    {
        if ($session->type === 'SUBSCRIPTION') {
            return UserSubscription::query()
                ->where('user_id', $user->id)
                ->where('status', 'ACTIVE')
                ->where('expires_at', '>', now())
                ->exists();
        }

        if ($session->media_type !== 'MOVIE' && $session->media_type !== 'TV_SHOW') {
            return false;
        }

        $modelClass = $session->media_type === 'MOVIE' ? Movie::class : TVShow::class;

        if ($session->type === 'BUY') {
            return UserPurchase::query()
                ->where('user_id', $user->id)
                ->where('purchasable_type', $modelClass)
                ->where('purchasable_id', $session->media_id)
                ->exists();
        }

        if ($session->type === 'RENT') {
            return UserRental::query()
                ->where('user_id', $user->id)
                ->where('rentable_type', $modelClass)
                ->where('rentable_id', $session->media_id)
                ->where('is_active', true)
                ->where('expires_at', '>', now())
                ->exists();
        }

        return false;
    }

    private function formatCheckoutSession(TvCheckoutSession $session, bool $withUrls = false): array
    {
        $checkoutPath = '/tv/checkout/' . $session->uuid;
        $checkoutUrl = rtrim($this->frontendUrl(), '/') . $checkoutPath;
        $transaction = $session->transaction_ref
            ? PaymentTransaction::query()
                ->where('user_id', $session->user_id)
                ->where('transaction_ref', $session->transaction_ref)
                ->first()
            : null;
        $accessGranted = $session->user
            ? $this->checkoutSessionHasAccess($session, $session->user)
            : false;

        return [
            'id' => $session->uuid,
            'status' => $session->status,
            'payment_status' => $transaction?->status,
            'access_granted' => $accessGranted,
            'next_poll_seconds' => $session->status === TvCheckoutSession::STATUS_PENDING_PAYMENT ? 3 : 5,
            'failure_reason' => $transaction?->failure_reason,
            'type' => $session->type,
            'title' => $session->title,
            'amount' => $session->amount !== null ? (float) $session->amount : null,
            'transaction_ref' => $session->transaction_ref,
            'expires_at' => $session->expires_at?->toIso8601String(),
            'completed_at' => $session->completed_at?->toIso8601String(),
            'device' => $this->formatDevice($session->device),
            'subscription_plan' => $session->subscriptionPlan ? [
                'id' => $session->subscriptionPlan->id,
                'name' => $session->subscriptionPlan->name,
                'price' => (float) $session->subscriptionPlan->price,
            ] : null,
            'content' => $session->media_id ? [
                'media_id' => $session->media_id,
                'media_type' => $session->media_type,
            ] : null,
            'checkout_path' => $checkoutPath,
            'checkout_url' => $withUrls ? $checkoutUrl : null,
        ];
    }

    private function formatUser(User $user): array
    {
        $activeSubscription = UserSubscription::query()
            ->where('user_id', $user->id)
            ->where('status', 'ACTIVE')
            ->where('expires_at', '>', now())
            ->with('subscriptionPlan')
            ->latest()
            ->first();

        $pendingSubscription = PaymentTransaction::query()
            ->where('user_id', $user->id)
            ->where('type', 'SUBSCRIPTION')
            ->where('status', 'PENDING')
            ->with('subscriptionPlan')
            ->latest()
            ->first();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'avatar' => $this->imageUrl($user->avatar),
            'plan' => $pendingSubscription?->subscriptionPlan?->name
                ?? $activeSubscription?->subscriptionPlan?->name
                ?? $user->plan
                ?? 'FREE',
            'planStatus' => $pendingSubscription
                ? 'PENDING'
                : ($activeSubscription ? 'ACTIVE' : ($user->plan_status ?? 'NONE')),
            'renewalDate' => $activeSubscription?->expires_at?->format('Y-m-d') ?? $user->renewal_date?->format('Y-m-d'),
            'emailVerified' => (bool) $user->email_verified_at,
        ];
    }

    private function formatDevice(?TvDevice $device): ?array
    {
        if (! $device) {
            return null;
        }

        return [
            'id' => $device->device_identifier,
            'name' => $device->name,
            'platform' => $device->platform,
            'app_version' => $device->app_version,
            'activated_at' => $device->activated_at?->toIso8601String(),
            'last_seen_at' => $device->last_seen_at?->toIso8601String(),
        ];
    }

    private function formatWatchHistoryCard(WatchHistory $history): ?array
    {
        $media = $history->media_type === 'TV_SHOW' ? $history->tvShow : $history->media;
        if (! $media) {
            return null;
        }

        $title = $media->title ?? 'Unknown';
        $subtitle = null;
        $route = '/watch/movie/' . $media->id;

        if ($history->episode && $history->episode->season && $history->episode->season->tvShow) {
            $title = $history->episode->season->tvShow->title;
            $subtitle = 'S' . $history->episode->season->number . ' E' . $history->episode->number . ' • ' . ($history->episode->title ?: 'Episode');
            $route = '/watch/tv-show/' . $history->episode->season->tvShow->id . '?episode=' . $history->episode->id;
        }

        return [
            'id' => 'history-' . $history->id,
            'type' => 'continue_watching',
            'title' => $title,
            'subtitle' => $subtitle,
            'thumbnail' => $this->imageUrl($media->thumbnail ?? $history->episode?->thumbnail),
            'backdrop' => $this->imageUrl($media->backdrop ?? $media->thumbnail ?? $history->episode?->thumbnail),
            'progress' => [
                'seconds' => $history->progress_seconds,
                'total_seconds' => $history->total_seconds,
                'percent' => $history->total_seconds > 0
                    ? (int) round(($history->progress_seconds / max($history->total_seconds, 1)) * 100)
                    : null,
            ],
            'route' => $route,
            'media_id' => $media->id,
            'media_type' => $history->episode_id ? 'TV_SHOW' : 'MOVIE',
            'episode_id' => $history->episode_id,
        ];
    }

    private function formatMovieCard(Movie $movie, bool $hero = false): array
    {
        $rawMediaType = strtoupper(str_replace('-', '_', (string) ($movie->media_type ?? 'MOVIE')));
        $isSeries = in_array($rawMediaType, ['TV_SHOW', 'TVSHOW', 'SERIES'], true);

        return [
            'id' => 'movie-' . $movie->id,
            'entity_id' => $movie->id,
            'type' => 'movie',
            'media_type' => $isSeries ? 'TV_SHOW' : 'MOVIE',
            'title' => $movie->title,
            'subtitle' => $movie->vj?->name ?: $movie->mediaLibrary?->name,
            'description' => $movie->description,
            'thumbnail' => $this->imageUrl($movie->thumbnail),
            'backdrop' => $this->imageUrl($movie->backdrop ?: $movie->thumbnail),
            'rating' => $movie->rating ? (float) $movie->rating : null,
            'genre' => $movie->genres?->pluck('name')->values()->all() ?? [],
            'access' => [
                'is_free' => (bool) $movie->is_free,
                'is_premium' => (bool) $movie->is_premium,
                'price_rent' => $movie->price_rent ? (float) $movie->price_rent : null,
                'price_buy' => $movie->price_buy ? (float) $movie->price_buy : null,
            ],
            'badge' => $hero
                ? 'Featured'
                : ($isSeries ? 'Series' : ($movie->is_free ? 'Free' : ($movie->is_premium ? 'Premium' : null))),
            'route' => '/movies/' . $movie->id,
            'watch_route' => $isSeries ? null : '/watch/movie/' . $movie->id,
        ];
    }

    private function formatTvShowCard(TVShow $show): array
    {
        return [
            'id' => 'tv-show-' . $show->id,
            'entity_id' => $show->id,
            'type' => 'tv_show',
            'title' => $show->title,
            'subtitle' => $show->vj?->name ?: $show->mediaLibrary?->name,
            'description' => $show->description,
            'thumbnail' => $this->imageUrl($show->thumbnail),
            'backdrop' => $this->imageUrl($show->backdrop ?: $show->thumbnail),
            'rating' => $show->rating ? (float) $show->rating : null,
            'genre' => $show->genres?->pluck('name')->values()->all() ?? [],
            'access' => [
                'is_free' => (bool) $show->is_free,
                'is_premium' => (bool) $show->is_premium,
                'price_rent' => $show->price_rent ? (float) $show->price_rent : null,
                'price_buy' => $show->price_buy ? (float) $show->price_buy : null,
            ],
            'badge' => $show->is_free ? 'Free' : ($show->is_premium ? 'Premium' : 'Series'),
            'route' => '/shows/' . $show->id,
            'watch_route' => '/watch/tv-show/' . $show->id,
        ];
    }

    private function formatLiveStreamCard(LiveStream $stream): array
    {
        return [
            'id' => 'live-' . $stream->id,
            'entity_id' => $stream->id,
            'type' => 'live_stream',
            'title' => $stream->title,
            'subtitle' => $stream->platform,
            'description' => $stream->description,
            'thumbnail' => $this->imageUrl($stream->thumbnail),
            'backdrop' => $this->imageUrl($stream->thumbnail),
            'badge' => $stream->is_live ? 'LIVE' : 'Replay',
            'viewer_count' => $stream->viewer_count,
            'route' => '/live/' . $stream->id,
            'watch_route' => '/watch/live-stream/' . $stream->id,
        ];
    }

    private function formatArticleCard(Article $article): array
    {
        $summary = $this->articlePresenter->summary($article);

        return [
            'id' => 'article-' . $article->id,
            'entity_id' => $article->id,
            'type' => 'article',
            'title' => $summary['title'] ?? $article->title,
            'subtitle' => $summary['category']['name'] ?? $article->category,
            'description' => $summary['excerpt'] ?? $article->excerpt,
            'thumbnail' => $this->imageUrl($summary['image'] ?? $article->image),
            'backdrop' => $this->imageUrl($summary['image'] ?? $article->image),
            'badge' => $article->is_top_news ? 'Top Story' : ucfirst((string) ($article->post_type ?? 'news')),
            'route' => '/articles/' . $article->id,
        ];
    }

    private function imageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return asset('storage/' . ltrim($path, '/'));
    }

    private function frontendUrl(): string
    {
        return (string) (config('app.frontend_url') ?: env('FRONTEND_URL', 'http://localhost:3000'));
    }
}
