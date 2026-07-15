<?php

namespace App\Http\Middleware;

use App\Models\OnlineVisitor;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class TrackOnlinePresence
{
    private const GUEST_COOKIE = 'narabox_guest_id';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->shouldTrack($request, $response)) {
            return $response;
        }

        $platform = $this->detectPlatform($request);
        $user = $request->user() ?? Auth::guard('sanctum')->user();
        $providedGuestId = $this->extractProvidedGuestId($request);
        [$visitorKey, $guestId, $shouldPersistGuestCookie] = $this->resolveVisitorIdentity(
            $request,
            $platform,
            $user?->id,
            $providedGuestId
        );

        try {
            if ($user?->id && filled($providedGuestId)) {
                OnlineVisitor::query()
                    ->where('platform', $platform)
                    ->where('guest_id', $providedGuestId)
                    ->whereNull('user_id')
                    ->delete();
            }

            OnlineVisitor::query()->updateOrCreate(
                ['visitor_key' => $visitorKey],
                [
                    'user_id' => $user?->id,
                    'platform' => $platform,
                    'guest_id' => $guestId,
                    'ip_address' => $request->ip(),
                    'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
                    'last_path' => Str::limit('/' . ltrim($request->path(), '/'), 255, ''),
                    'last_seen_at' => now(),
                ]
            );
        } catch (Throwable) {
            return $response;
        }

        if ($shouldPersistGuestCookie && $guestId) {
            $response->headers->setCookie(Cookie::make(
                self::GUEST_COOKIE,
                $guestId,
                60 * 24 * 365 * 2,
                config('session.path', '/'),
                config('session.domain'),
                (bool) config('session.secure'),
                true,
                false,
                config('session.same_site', 'lax')
            ));
        }

        return $response;
    }

    private function shouldTrack(Request $request, Response $response): bool
    {
        if ($request->isMethod('OPTIONS')) {
            return false;
        }

        if ($response->getStatusCode() >= 500) {
            return false;
        }

        foreach ([
            'admin*',
            'livewire*',
            '_ignition*',
            'storage*',
            'up',
            'api/cdn/*',
            'api/telegram/*',
            'api/v1/worker/*',
            'sanctum/csrf-cookie',
        ] as $pattern) {
            if ($request->is($pattern)) {
                return false;
            }
        }

        return true;
    }

    private function detectPlatform(Request $request): string
    {
        if (! $request->is('api/*')) {
            return 'web';
        }

        $originHost = $this->extractHostFromHeader(
            $request->headers->get('Origin') ?: $request->headers->get('Referer')
        );

        if (in_array($originHost, [
            'localhost',
            '127.0.0.1',
            'naraboxtv.com',
            'www.naraboxtv.com',
            'portal.naraboxtv.com',
        ], true)) {
            return 'web';
        }

        if (filled($request->headers->get('Sec-Fetch-Site'))) {
            return 'web';
        }

        return 'app';
    }

    private function resolveVisitorIdentity(Request $request, string $platform, ?int $userId, ?string $providedGuestId): array
    {
        if ($userId) {
            return ["user:{$platform}:{$userId}", $providedGuestId, false];
        }

        $guestId = $providedGuestId;
        $shouldPersistGuestCookie = false;

        if (blank($guestId)) {
            if ($platform === 'web') {
                $guestId = (string) Str::uuid();
                $shouldPersistGuestCookie = true;
            } else {
                $guestId = $this->buildGuestFingerprint($request, $platform);
            }
        }

        return ["guest:{$platform}:{$guestId}", $guestId, $shouldPersistGuestCookie];
    }

    private function extractProvidedGuestId(Request $request): ?string
    {
        $candidates = [
            $request->cookie(self::GUEST_COOKIE),
            $request->header('X-Guest-Id'),
            $request->header('X-Device-Id'),
            $request->header('X-Session-Uuid'),
            $request->input('guest_id'),
            $request->input('device_id'),
            $request->input('session_uuid'),
            $request->hasSession() ? $request->session()->getId() : null,
        ];

        foreach ($candidates as $candidate) {
            $candidate = is_string($candidate) ? trim($candidate) : null;

            if (filled($candidate)) {
                return Str::limit($candidate, 64, '');
            }
        }

        return null;
    }

    private function buildGuestFingerprint(Request $request, string $platform): string
    {
        $fingerprint = hash('sha256', implode('|', [
            $platform,
            (string) $request->ip(),
            (string) $request->userAgent(),
        ]));

        return substr($fingerprint, 0, 64);
    }

    private function extractHostFromHeader(?string $headerValue): ?string
    {
        if (blank($headerValue)) {
            return null;
        }

        $host = parse_url($headerValue, PHP_URL_HOST);

        return is_string($host) ? strtolower($host) : null;
    }
}
