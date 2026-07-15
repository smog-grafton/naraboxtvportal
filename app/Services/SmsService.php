<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class SmsService
{
    /**
     * Send an OTP code to a phone number.
     *
     * This is intentionally provider-agnostic. In production, you can plug in
     * an SMS gateway here (eg. Africa's Talking, Twilio). For now, we log the
     * SMS so local/dev environments can see the code.
     */
    public static function sendOtp(string $phone, string $code): bool
    {
        // Placeholder implementation: log the OTP for debugging.
        Log::info('Sending OTP via SmsService', [
            'phone' => $phone,
            'code' => $code,
        ]);

        // TODO: Replace with real SMS provider integration, controlled by config/env.
        return true;
    }
}

