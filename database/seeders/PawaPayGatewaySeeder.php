<?php

namespace Database\Seeders;

use App\Models\PaymentGateway;
use Illuminate\Database\Seeder;

class PawaPayGatewaySeeder extends Seeder
{
    public function run(): void
    {
        $appUrl = rtrim((string) config('app.url', env('APP_URL', 'http://127.0.0.1:8000')), '/');

        PaymentGateway::updateOrCreate(
            ['slug' => 'pawapay'],
            [
                'name' => 'PawaPay',
                'slug' => 'pawapay',
                'code' => 'pawapay',
                'type' => 'AUTOMATIC',
                'display_name' => 'PawaPay (MTN/Airtel)',
                'description' => 'Collect mobile money payments via PawaPay deposits.',
                'helper_text' => 'MTN & Airtel Mobile Money',
                'is_active' => true,
                'sort_order' => 7,
                'config' => [
                    // Dummy but realistic architecture values - replace in production.
                    'environment' => 'sandbox',
                    'base_url' => 'https://api.sandbox.pawapay.io',
                    'default_currency' => 'UGX',
                    'providers' => ['MTN_MOMO_UGA', 'AIRTEL_OAPI_UGA'],
                    'callback_urls' => [
                        'deposits' => $appUrl . '/api/v1/webhooks/pawapay/deposits',
                        'refunds' => $appUrl . '/api/v1/webhooks/pawapay/refunds',
                    ],
                    'notes' => 'Token is read from PAWAPAY_API_TOKEN in .env',
                ],
            ]
        );
    }
}

