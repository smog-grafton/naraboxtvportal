<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Ensure CORS is handled first for all API routes
        $middleware->api(prepend: [
            \App\Http\Middleware\HandleCors::class,
        ]);

        // Also add CORS to web routes for storage files
        $middleware->web(prepend: [
            \App\Http\Middleware\HandleCors::class,
        ]);

        $trustedProxies = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('SECURITY_TRUSTED_PROXIES', ''))
        )));
        if ($trustedProxies !== []) {
            $middleware->trustProxies(
                at: $trustedProxies,
                headers: Request::HEADER_X_FORWARDED_FOR
                    | Request::HEADER_X_FORWARDED_HOST
                    | Request::HEADER_X_FORWARDED_PORT
                    | Request::HEADER_X_FORWARDED_PROTO
            );
        }

        // Register middleware aliases
        $middleware->alias([
            'email.verified' => \App\Http\Middleware\EnsureEmailVerified::class,
            'worker.api' => \App\Http\Middleware\AuthenticateWorkerApi::class,
            'app.api_key' => \App\Http\Middleware\ValidateApiKey::class,
            'platform.operations' => \App\Http\Middleware\EnforcePlatformOperations::class,
            'account.security' => \App\Http\Middleware\EnforceAccountSecurity::class,
            'payment.security' => \App\Http\Middleware\EnforcePaymentSecurity::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => 'Authentication is required.',
                    'code' => 'SESSION_REAUTH_REQUIRED',
                ], 401);
            }
        });
    })->create();
