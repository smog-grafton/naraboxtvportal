<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HandleCors
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Handle preflight OPTIONS request
        if ($request->getMethod() === 'OPTIONS') {
            $origin = $request->headers->get('Origin');
            $allowedOrigins = [
                'http://localhost',
                'http://127.0.0.1',
                'http://localhost:3000',
                'http://127.0.0.1:3000',
                'http://localhost:7000',
                'http://127.0.0.1:7000',
                'http://localhost:8000',
                'http://127.0.0.1:8000',
                'https://naraboxtv.com',
                'https://www.naraboxtv.com',
                'https://portal.naraboxtv.com',
            ];
            
            $isAllowed = false;
            if ($origin) {
                foreach ($allowedOrigins as $allowedOrigin) {
                    if ($origin === $allowedOrigin) {
                        $isAllowed = true;
                        break;
                    }
                }
                
                if (!$isAllowed) {
                // Match localhost with or without port (including 7000)
                if (preg_match('#^http://localhost(:\d+)?$#', $origin) || 
                    preg_match('#^http://127\.0\.0\.1(:\d+)?$#', $origin)) {
                        $isAllowed = true;
                    }
                }
            }
            
            return response('', 200)
                ->header('Access-Control-Allow-Origin', $isAllowed || !$origin ? ($origin ?: '*') : '*')
                ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS, PATCH')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin, X-API-KEY, X-Api-Key')
                ->header('Access-Control-Allow-Credentials', 'true')
                ->header('Access-Control-Max-Age', '86400');
        }

        $response = $next($request);

        // Add CORS headers to the response
        $origin = $request->headers->get('Origin');
        
        // Check if origin is allowed
        $allowedOrigins = [
            'http://localhost',
            'http://127.0.0.1',
            'http://localhost:3000',
            'http://127.0.0.1:3000',
            'http://localhost:8000',
            'http://127.0.0.1:8000',
            'https://naraboxtv.com',
            'https://www.naraboxtv.com',
            'https://portal.naraboxtv.com',
        ];

        // Check if origin matches pattern
        $isAllowed = false;
        if ($origin) {
            foreach ($allowedOrigins as $allowedOrigin) {
                if ($origin === $allowedOrigin) {
                    $isAllowed = true;
                    break;
                }
            }
            
            // Check patterns - match localhost with or without port and main domains
            if (!$isAllowed) {
                if (preg_match('#^http://localhost(:\d+)?$#', $origin) || 
                    preg_match('#^http://127\.0\.0\.1(:\d+)?$#', $origin) ||
                    preg_match('#^https?://(www\.)?naraboxtv\.com$#', $origin) ||
                    preg_match('#^https?://portal\.naraboxtv\.com$#', $origin)) {
                    $isAllowed = true;
                }
            }
        }

        if ($isAllowed || !$origin) {
            $response->headers->set('Access-Control-Allow-Origin', $origin ?: '*');
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS, PATCH');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin, X-API-KEY, X-Api-Key');
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
            $response->headers->set('Access-Control-Expose-Headers', '');
        }

        return $response;
    }
}

