<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            [
                'name' => 'verification_code',
                'subject' => 'NaraBox - Email Verification Code',
                'body' => '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; background: #121010; color: #fff; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #1a1a1a; border: 2px solid #038C65; padding: 40px; }
        .header { text-align: center; margin-bottom: 30px; }
        .code-box { background: #121010; border: 3px solid #038C65; padding: 20px 40px; text-align: center; font-size: 48px; font-weight: 900; letter-spacing: 8px; color: #038C65; font-family: "Oswald", sans-serif; margin: 40px 0; }
        .footer { text-align: center; margin-top: 40px; color: #666; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 style="color: #038C65; font-family: Oswald, sans-serif; text-transform: uppercase;">NaraBox Email Verification</h1>
            <p style="color: #038C65; text-transform: uppercase; font-weight: bold;">OPERATOR_IDENTIFICATION_REQUIRED</p>
        </div>
        <p>Your verification code is:</p>
        <div class="code-box">{{code}}</div>
        <p><strong>SECURITY_PROTOCOL:</strong> This code will expire in 15 minutes. Do not share this code with anyone.</p>
        <p><strong>MISSION_STATUS:</strong> Enter this code in the verification screen to complete your registration.</p>
        <div class="footer">
            <p>If you did not request this code, please ignore this email.</p>
            <p>© ' . date('Y') . ' NaraBox. All rights reserved.</p>
        </div>
    </div>
</body>
</html>',
                'variables' => [
                    'code' => '6-digit verification code',
                ],
                'is_active' => true,
            ],
            [
                'name' => 'welcome',
                'subject' => 'Welcome to NaraBox, {{name}}!',
                'body' => '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; background: #121010; color: #fff; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #1a1a1a; border: 2px solid #038C65; padding: 40px; }
        .header { text-align: center; margin-bottom: 30px; }
        .footer { text-align: center; margin-top: 40px; color: #666; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 style="color: #038C65; font-family: Oswald, sans-serif; text-transform: uppercase;">Welcome to NaraBox</h1>
        </div>
        <p>Hello <strong>{{name}}</strong>,</p>
        <p>Your account has been successfully verified and activated!</p>
        <p>You now have full access to the NaraBox streaming platform. Start exploring our vast collection of movies, TV shows, and exclusive content.</p>
        <p style="color: #038C65; font-weight: bold;">ACCESS GRANTED - Welcome to the network, Operator.</p>
        <div class="footer">
            <p>© ' . date('Y') . ' NaraBox. All rights reserved.</p>
        </div>
    </div>
</body>
</html>',
                'variables' => [
                    'name' => 'User\'s full name',
                ],
                'is_active' => true,
            ],
            [
                'name' => 'payment_success',
                'subject' => 'NaraBox - Payment Successful',
                'body' => '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; background: #121010; color: #fff; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #1a1a1a; border: 2px solid #038C65; padding: 40px; }
        .header { text-align: center; margin-bottom: 30px; }
        .details { background: #121010; padding: 20px; margin: 20px 0; border-left: 3px solid #038C65; }
        .footer { text-align: center; margin-top: 40px; color: #666; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 style="color: #038C65; font-family: Oswald, sans-serif; text-transform: uppercase;">Payment Successful</h1>
        </div>
        <p>Hello <strong>{{name}}</strong>,</p>
        <p>Your payment has been successfully processed!</p>
        <div class="details">
            <p><strong>Transaction Reference:</strong> {{transaction_ref}}</p>
            <p><strong>Payment Type:</strong> {{payment_type}}</p>
            <p><strong>Amount:</strong> UGX {{amount}}</p>
            <p><strong>Payment Method:</strong> {{payment_method}}</p>
            <p><strong>Date:</strong> {{date}}</p>
        </div>
        <p style="color: #038C65; font-weight: bold;">Your content access has been activated. Enjoy your streaming experience!</p>
        <div class="footer">
            <p>© ' . date('Y') . ' NaraBox. All rights reserved.</p>
        </div>
    </div>
</body>
</html>',
                'variables' => [
                    'name' => 'User\'s full name',
                    'transaction_ref' => 'Transaction reference number',
                    'payment_type' => 'RENT, BUY, or SUBSCRIPTION',
                    'amount' => 'Payment amount',
                    'payment_method' => 'Payment gateway name',
                    'date' => 'Payment date',
                ],
                'is_active' => true,
            ],
            [
                'name' => 'password_reset',
                'subject' => 'NaraBox - Reset Your Password',
                'body' => '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; background: #121010; color: #fff; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #1a1a1a; border: 2px solid #038C65; padding: 40px; }
        .header { text-align: center; margin-bottom: 30px; }
        .button { display: inline-block; background: #038C65; color: #fff; padding: 15px 30px; text-decoration: none; font-weight: bold; margin: 20px 0; }
        .footer { text-align: center; margin-top: 40px; color: #666; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 style="color: #038C65; font-family: Oswald, sans-serif; text-transform: uppercase;">Password Reset Request</h1>
        </div>
        <p>Hello,</p>
        <p>You requested to reset your password for your NaraBox account ({{email}}).</p>
        <p>Click the button below to reset your password:</p>
        <div style="text-align: center;">
            <a href="{{reset_url}}" class="button">RESET PASSWORD</a>
        </div>
        <p>Or copy and paste this link into your browser:</p>
        <p style="word-break: break-all; color: #038C65;">{{reset_url}}</p>
        <p><strong>SECURITY_PROTOCOL:</strong> This link will expire in 1 hour. If you did not request this, please ignore this email.</p>
        <div class="footer">
            <p>© ' . date('Y') . ' NaraBox. All rights reserved.</p>
        </div>
    </div>
</body>
</html>',
                'variables' => [
                    'email' => 'User\'s email address',
                    'token' => 'Reset token',
                    'reset_url' => 'Password reset URL',
                ],
                'is_active' => true,
            ],
        ];

        foreach ($templates as $template) {
            EmailTemplate::updateOrCreate(
                ['name' => $template['name']],
                $template
            );
        }

        $this->command->info('Email templates seeded successfully!');
    }
}
