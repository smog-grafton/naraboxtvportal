<?php

namespace Tests\Feature;

use App\Models\PaymentGateway;
use App\Services\IoTeCService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IoTeCServiceCardTest extends TestCase
{
    private function gateway(string $walletId = '5e83b187-801e-410e-b76e-f491928547e0'): PaymentGateway
    {
        return new PaymentGateway([
            'slug' => 'iotec',
            'config' => [
                'client_id' => 'test-client',
                'client_secret' => 'test-secret',
                'wallet_id' => $walletId,
            ],
        ]);
    }

    public function test_collect_card_sends_email_payer_with_card_category_and_parses_card_redirect_url(): void
    {
        Http::fake([
            '*/connect/token' => Http::response(['access_token' => 'tok', 'expires_in' => 300], 200),
            '*/api/collections/collect' => Http::response([
                'id' => 'req-1',
                'status' => 'Pending',
                'cardRedirectUrl' => 'https://pay.iotec.io/pegpay/checkout/req-1',
                'vendor' => 'MasterCard',
            ], 200),
        ]);

        $service = new IoTeCService($this->gateway());
        $result = $service->collectCard(
            'NBX-IOT-TEST-1',
            5000,
            'jane@example.com',
            'Jane Doe',
            'https://narabox.example.com/payment/callback?tx_ref=NBX-IOT-TEST-1'
        );

        $this->assertSame('req-1', $result['request_id']);
        $this->assertSame('https://pay.iotec.io/pegpay/checkout/req-1', $result['card_redirect_url']);
        $this->assertSame('MasterCard', $result['vendor']);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/api/collections/collect')) {
                return false;
            }
            $data = $request->data();

            return $data['payer'] === 'jane@example.com'
                && $data['payerName'] === 'Jane Doe'
                && $data['category'] === 'Card'
                && $data['redirectUrl'] === 'https://narabox.example.com/payment/callback?tx_ref=NBX-IOT-TEST-1';
        });
    }

    public function test_collect_card_rejects_invalid_email(): void
    {
        Http::fake(['*/connect/token' => Http::response(['access_token' => 'tok', 'expires_in' => 300], 200)]);

        $service = new IoTeCService($this->gateway());
        $result = $service->collectCard('NBX-IOT-TEST-2', 5000, 'not-an-email', 'Jane Doe', 'https://example.com/cb');

        $this->assertArrayHasKey('error', $result);
    }

    public function test_collect_card_rejects_amount_below_minimum(): void
    {
        Http::fake(['*/connect/token' => Http::response(['access_token' => 'tok', 'expires_in' => 300], 200)]);

        $service = new IoTeCService($this->gateway());
        $result = $service->collectCard('NBX-IOT-TEST-3', 100, 'jane@example.com', 'Jane Doe', 'https://example.com/cb');

        $this->assertArrayHasKey('error', $result);
    }

    public function test_collect_card_requires_wallet_id(): void
    {
        Http::fake(['*/connect/token' => Http::response(['access_token' => 'tok', 'expires_in' => 300], 200)]);

        $service = new IoTeCService($this->gateway(''));
        $result = $service->collectCard('NBX-IOT-TEST-4', 5000, 'jane@example.com', 'Jane Doe', 'https://example.com/cb');

        $this->assertArrayHasKey('error', $result);
    }
}
