<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

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
        
        // Register middleware aliases
        $middleware->alias([
            'email.verified' => \App\Http\Middleware\EnsureEmailVerified::class,
            'worker.api' => \App\Http\Middleware\AuthenticateWorkerApi::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
