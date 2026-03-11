<?php

namespace Database\Seeders;

use App\Models\PaymentGateway;
use Illuminate\Database\Seeder;

class PaymentGatewaySeeder extends Seeder
{
    public function run(): void
    {
        $gateways = [
            [
                'name' => 'MTN',
                'slug' => 'mtn',
                'code' => 'mtn',
                'display_name' => 'MTN MoMo',
                'description' => 'Mobile money payment via MTN',
                'helper_text' => 'Pay with MTN Mobile Money',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'AIRTEL',
                'slug' => 'airtel',
                'code' => 'airtel',
                'display_name' => 'Airtel Money',
                'description' => 'Mobile money payment via Airtel',
                'helper_text' => 'Pay with Airtel Money',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'STRIPE',
                'slug' => 'stripe',
                'code' => 'stripe',
                'display_name' => 'Card Payment',
                'description' => 'Credit and debit card payments',
                'helper_text' => 'Pay with your debit or credit card',
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'PAYPAL',
                'slug' => 'paypal',
                'code' => 'paypal',
                'display_name' => 'PayPal Express',
                'description' => 'PayPal payment gateway',
                'helper_text' => 'Pay securely with PayPal',
                'is_active' => true,
                'sort_order' => 4,
            ],
        ];

        foreach ($gateways as $gateway) {
            PaymentGateway::firstOrCreate(['slug' => $gateway['slug']], $gateway);
        }

        // Add Flutterwave gateway
        PaymentGateway::firstOrCreate(
            ['slug' => 'flutterwave'],
            [
                'name' => 'Flutterwave',
                'slug' => 'flutterwave',
                'code' => 'flutterwave',
                'type' => 'AUTOMATIC',
                'display_name' => 'Flutterwave',
                'description' => 'Pay with card or mobile money via Flutterwave',
                'helper_text' => 'Card and mobile money payments',
                'is_active' => true,
                'sort_order' => 5,
                'config' => [
                    'public_key' => env('FLW_PUBLIC_KEY', 'FLWPUBK-5f999063dffb2c9a9bde9d51e9fbd151-X'),
                    'secret_key' => env('FLW_SECRET_KEY', 'FLWSECK-684fe6433aaf89bc83f246bb3fddaa52-19b78b572d5vt-X'),
                    'encryption_key' => env('FLW_ENCRYPTION_KEY', '684fe6433aaf74392998267f'),
                    'env' => env('FLW_ENV', 'live'),
                    'currency' => env('FLW_CURRENCY', 'UGX'),
                ],
            ]
        );

        // Add ioTec Pay gateway (credentials from env; editable in Filament)
        PaymentGateway::firstOrCreate(
            ['slug' => 'iotec'],
            [
                'name' => 'ioTec Pay',
                'slug' => 'iotec',
                'code' => 'iotec',
                'type' => 'AUTOMATIC',
                'display_name' => 'ioTec Pay',
                'description' => 'Pay with mobile money via ioTec (in-site prompt, no redirect)',
                'helper_text' => 'In-site phone prompt payment',
                'is_active' => true,
                'sort_order' => 6,
                'config' => [
                    'client_id' => env('IOTEC_CLIENT_ID', ''),
                    'client_secret' => env('IOTEC_CLIENT_SECRET', ''),
                    'grant_type' => env('IOTEC_GRANT_TYPE', 'client_credentials'),
                    'id_base_url' => env('IOTEC_ID_BASE_URL', 'https://id.iotec.io'),
                    'pay_base_url' => env('IOTEC_PAY_BASE_URL', 'https://pay.iotec.io'),
                    'wallet_id' => env('IOTEC_WALLET_ID', ''),
                ],
            ]
        );

        // Add PawaPay gateway
        PaymentGateway::firstOrCreate(
            ['slug' => 'pawapay'],
            [
                'name' => 'PawaPay',
                'slug' => 'pawapay',
                'code' => 'pawapay',
                'type' => 'AUTOMATIC',
                'display_name' => 'PawaPay (MTN/Airtel)',
                'description' => 'Collect mobile money payments via PawaPay deposits',
                'helper_text' => 'MTN & Airtel Mobile Money',
                'is_active' => true,
                'sort_order' => 7,
            ]
        );
    }
}
