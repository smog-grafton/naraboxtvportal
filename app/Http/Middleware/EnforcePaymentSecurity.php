<?php

namespace App\Http\Middleware;

use App\Services\PaymentSecurityService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforcePaymentSecurity
{
    public function __construct(private readonly PaymentSecurityService $security) {}

    public function handle(Request $request, Closure $next): Response
    {
        $guard = $this->security->begin($request);
        if ($guard['response']) {
            $guard['lock']?->release();
            return $guard['response'];
        }

        try {
            return $next($request);
        } finally {
            $guard['lock']?->release();
        }
    }
}
