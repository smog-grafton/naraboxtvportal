<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Application API Key
    |--------------------------------------------------------------------------
    |
    | This key is used to protect app-facing API routes (mobile/web clients).
    | Set APP_API_KEY in your .env and send it with each request using the
    | configured header name (by default: X-API-KEY).
    |
    | Example:
    |   APP_API_KEY=your-long-random-key-here
    |
    */

    'key' => env('APP_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | API Key Header Name
    |--------------------------------------------------------------------------
    |
    | The HTTP header name expected on protected requests. Use an explicit
    | app-scoped header rather than overloading Authorization.
    |
    */

    'header' => env('APP_API_KEY_HEADER', 'X-API-KEY'),

    /*
    |--------------------------------------------------------------------------
    | Enable API Key Protection
    |--------------------------------------------------------------------------
    |
    | Allows you to turn API key enforcement on or off without changing code.
    | When disabled or when no key is configured, the middleware will allow
    | all requests to pass through.
    |
    */

    'enabled' => (bool) env('APP_API_KEY_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Email / phone verification codes (SMS & email OTP)
    |--------------------------------------------------------------------------
    |
    | When false (default for MVP), registration returns a token immediately,
    | email is marked verified, phone sign-in does not send or check OTP codes.
    | Set AUTH_REQUIRE_VERIFICATION_CODES=true when you are ready for production
    | email verification and SMS OTP flows.
    |
    */

    'require_verification_codes' => (bool) env('AUTH_REQUIRE_VERIFICATION_CODES', false),

    /*
    |--------------------------------------------------------------------------
    | Web Auth Bridge
    |--------------------------------------------------------------------------
    |
    | One-time short-lived tokens used to transfer authenticated users from
    | mobile app -> browser checkout flow safely.
    |
    */
    'web_bridge_ttl_minutes' => (int) env('WEB_BRIDGE_TTL_MINUTES', 2),
];

