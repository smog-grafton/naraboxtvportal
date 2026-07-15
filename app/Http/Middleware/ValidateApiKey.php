<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateApiKey
{
    /**
    * Handle an incoming request.
    */
    public function handle(Request $request, Closure $next): Response
    {
        // Browsers can't reliably send custom headers (like X-API-KEY) for direct file
        // downloads triggered via navigation/window.open. Download access control is
        // already handled inside DownloadController (free/public vs paid + access_token).
        if ($request->is('api/v1/downloads/*')) {
            return $next($request);
        }

        // Google OAuth web flow: the browser is redirected to our callback URL by Google
        // with no way to attach X-API-KEY. CSRF is handled by Socialite state; the code
        // exchange is server-side.
        if ($request->is('api/v1/auth/google/callback', 'api/v1/auth/google')) {
            return $next($request);
        }

        // If API key protection is disabled or no key is configured, allow all.
        if (!config('api.enabled')) {
            return $next($request);
        }

        $expectedKey = config('api.key');

        if (empty($expectedKey)) {
            return $next($request);
        }

        $headerName = config('api.header', 'X-API-KEY');
        $providedKey = $request->header($headerName)
            ?: $request->header('X-API-KEY')
            ?: $request->header('X-Api-Key');

        if (!$providedKey || !hash_equals($expectedKey, (string) $providedKey)) {
            return response()->json([
                'error' => 'Invalid or missing API key',
                'code' => 'INVALID_API_KEY',
                'message' => 'Your client is not authorized to call this API. Please include a valid API key in the request headers.',
            ], 401);
        }

        return $next($request);
    }
}

