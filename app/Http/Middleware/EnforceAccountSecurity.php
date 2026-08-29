<?php

namespace App\Http\Middleware;

use App\Services\AccountSecurityService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceAccountSecurity
{
    public function __construct(private readonly AccountSecurityService $security) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ($response = $this->security->blockingResponse($request->user()))) {
            return $response;
        }

        return $next($request);
    }
}
