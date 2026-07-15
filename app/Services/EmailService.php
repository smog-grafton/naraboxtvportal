<?php

namespace App\Services;

use App\Mail\DynamicMail;
use App\Models\SmtpSetting;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class EmailService
{
    /**
     * Send email using template
     */
    public static function send(string $to, string $templateName, array $data = []): bool
    {
        try {
            // Get active SMTP settings
            $smtpSettings = SmtpSetting::getActive();
            
            if ($smtpSettings) {
                // Apply SMTP settings to config
                $smtpSettings->applyToConfig();
            } else {
                // Fallback to .env settings if no DB settings
                Log::warning('No active SMTP settings found, using .env configuration');
            }

            // Send email using template
            Mail::to($to)->send(new DynamicMail($templateName, $data));
            
            Log::info('Email sent successfully', [
                'to' => $to,
                'template' => $templateName,
            ]);
            
            return true;
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            
            // Check for specific SMTP errors
            if (strpos($errorMessage, '554') !== false || strpos($errorMessage, 'Disabled by user') !== false) {
                Log::error('SMTP Account Disabled: The email account has been disabled in hPanel. Please enable it in Hostinger hPanel.', [
                    'to' => $to,
                    'template' => $templateName,
                    'error' => $errorMessage,
                ]);
            } elseif (strpos($errorMessage, '535') !== false || strpos($errorMessage, 'authentication') !== false) {
                Log::error('SMTP Authentication Failed: Check username and password in SMTP settings.', [
                    'to' => $to,
                    'template' => $templateName,
                    'error' => $errorMessage,
                ]);
            } else {
                Log::error('Email sending failed: ' . $errorMessage, [
                    'to' => $to,
                    'template' => $templateName,
                    'error' => $e->getTraceAsString(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
            }
            
            return false;
        }
    }

    /**
     * Send verification code email
     */
    public static function sendVerificationCode(string $to, string $code): bool
    {
        return self::send($to, 'verification_code', ['code' => $code]);
    }

    /**
     * Send welcome email
     */
    public static function sendWelcome(string $to, string $name): bool
    {
        return self::send($to, 'welcome', ['name' => $name, 'user_name' => $name]);
    }

    /**
     * Send payment success email
     */
    public static function sendPaymentSuccess(string $to, array $paymentData): bool
    {
        if (isset($paymentData['name']) && ! isset($paymentData['user_name'])) {
            $paymentData['user_name'] = $paymentData['name'];
        }

        return self::send($to, 'payment_success', $paymentData);
    }
}
