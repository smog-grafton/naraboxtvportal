<?php

namespace App\Http\Middleware;

use App\Models\MaintenanceWindow;
use App\Services\PlatformOperationsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforcePlatformOperations
{
    public function __construct(private readonly PlatformOperationsService $operations) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('api/v1/bootstrap')) {
            return $next($request);
        }

        $platform = $this->operations->normalizePlatform(
            $request->header('X-NaraBox-Platform', $request->input('platform'))
        );
        $build = (int) $request->header('X-NaraBox-Build', '0');
        $version = $request->header('X-NaraBox-Version');

        if ($build > 0) {
            $decision = $this->operations->versionDecision($platform, $build, $version);
            if (($decision['status'] ?? null) === 'required') {
                return response()->json([
                    'code' => 'APP_UPDATE_REQUIRED',
                    'status' => 'update_required',
                    'message' => $decision['message'],
                    'platform' => $platform,
                    'minimum_build' => $decision['minimum_build'],
                    'update_url' => $decision['update_url'],
                ], 426)->header('Cache-Control', 'no-store');
            }
        }

        $feature = $this->featureForRequest($request);
        foreach ($this->operations->maintenanceWindows($platform) as $window) {
            /** @var MaintenanceWindow $window */
            $isRead = in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true);
            $affected = $window->affected_features ?? [];
            $blocksFeature = in_array('all', $affected, true)
                || in_array($feature, $affected, true)
                || (in_array('payments', $affected, true) && in_array($feature, ['mobile_money', 'card_payments'], true));

            if ($window->mode === 'full' && ! ($window->allow_read_only && $isRead)) {
                return $this->maintenanceResponse($window, $platform, $feature, 'PLATFORM_MAINTENANCE');
            }
            if ($window->mode === 'read_only' && ! $isRead) {
                return $this->maintenanceResponse($window, $platform, $feature, 'READ_ONLY_MAINTENANCE');
            }
            if ($window->mode === 'partial' && $blocksFeature) {
                return $this->maintenanceResponse($window, $platform, $feature, 'FEATURE_MAINTENANCE');
            }
        }

        return $next($request);
    }

    private function maintenanceResponse(MaintenanceWindow $window, string $platform, string $feature, string $code): Response
    {
        return response()->json([
            'code' => $code,
            'status' => 'maintenance',
            'platform' => $platform,
            'feature' => $feature,
            'title' => $window->title,
            'message' => $window->message,
            'mode' => $window->mode,
            'available_features' => $window->available_features ?? [],
            'until' => $window->ends_at?->toIso8601String(),
            'revision' => $window->revision,
        ], 503)->header('Retry-After', $window->ends_at?->diffInSeconds(now()) ?: 60)
            ->header('Cache-Control', 'no-store');
    }

    private function featureForRequest(Request $request): string
    {
        $path = trim($request->path(), '/');

        return match (true) {
            str_contains($path, '/tv/auth/'), str_contains($path, '/tv/checkout/') => 'pairing',
            str_contains($path, '/auth/') => 'authentication',
            str_contains($path, '/player/'), str_contains($path, '/playback/'), str_contains($path, '/watch-history'), str_contains($path, '/views/track') => 'playback',
            str_contains($path, '/downloads/') => 'downloads',
            str_contains($path, '/iotec/'), str_contains($path, '/pawapay/') => 'mobile_money',
            str_contains($path, '/flutterwave/') => 'card_payments',
            str_contains($path, '/payments/') && $this->isMobileMoneyRequest($request) => 'mobile_money',
            str_contains($path, '/payments/') && $this->isCardPaymentRequest($request) => 'card_payments',
            str_contains($path, '/payments/'), str_contains($path, '/payment-gateways') => 'payments',
            str_contains($path, '/subscription') => 'subscriptions',
            str_contains($path, '/comments') => 'comments',
            str_contains($path, '/search') => 'search',
            str_contains($path, '/articles') => 'news',
            str_contains($path, '/vjs') => 'vj_pages',
            default => 'catalogue',
        };
    }

    private function isMobileMoneyRequest(Request $request): bool
    {
        $value = strtolower((string) ($request->input('gateway') ?? $request->input('provider') ?? $request->input('payment_method') ?? ''));

        return str_contains($value, 'mobile') || str_contains($value, 'momo') || str_contains($value, 'mtn')
            || str_contains($value, 'airtel') || str_contains($value, 'pawapay') || str_contains($value, 'iotec');
    }

    private function isCardPaymentRequest(Request $request): bool
    {
        $value = strtolower((string) ($request->input('gateway') ?? $request->input('provider') ?? $request->input('payment_method') ?? ''));

        return str_contains($value, 'card') || str_contains($value, 'flutterwave');
    }
}
