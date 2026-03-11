<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateWorkerApi
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) config('services.worker_api_token', '');
        if ($token === '') {
            return response()->json(['message' => 'Worker API not configured.'], 503);
        }

        $header = (string) $request->header('Authorization', '');
        $bearer = '';
        if (str_starts_with($header, 'Bearer ')) {
            $bearer = trim(substr($header, 7));
        }

        if ($bearer === '' || ! hash_equals($token, $bearer)) {
            return response()->json(['message' => 'Missing or invalid token.'], 401);
        }

        return $next($request);
    }
}
