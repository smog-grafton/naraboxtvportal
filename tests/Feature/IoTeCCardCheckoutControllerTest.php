<?php

namespace Tests\Feature;

use App\Models\Movie;
use App\Models\PaymentGateway;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IoTeCCardCheckoutControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // API key protection is orthogonal to the card-payment feature under test.
        config(['api.enabled' => false]);
    }

    private function makeGateway(?bool $cardEnabled = null): PaymentGateway
    {
        $config = [
            'client_id' => 'test-client',
            'client_secret' => 'test-secret',
            'wallet_id' => '5e83b187-801e-410e-b76e-f491928547e0',
        ];
        if ($cardEnabled !== null) {
            $config['card_enabled'] = $cardEnabled;
        }

        return PaymentGateway::create([
            'name' => 'ioTec Pay',
            'slug' => 'iotec',
            'code' => 'iotec',
            'type' => 'AUTOMATIC',
            'display_name' => 'ioTec Pay',
            'is_active' => true,
            'config' => $config,
        ]);
    }

    private function fakeIotecHttp(array $collectResponse): void
    {
        Http::fake([
            '*/connect/token' => Http::response(['access_token' => 'test-token', 'expires_in' => 300], 200),
            '*/api/collections/collect' => Http::response($collectResponse, 200),
        ]);
    }

    public function test_card_initiate_returns_card_redirect_url_and_creates_transaction(): void
    {
        $this->makeGateway();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $movie = Movie::factory()->create(['price_buy' => 5000]);

        $this->fakeIotecHttp([
            'id' => 'req-card-123',
            'status' => 'Pending',
            'cardRedirectUrl' => 'https://pay.iotec.io/pegpay/checkout/req-card-123',
            'vendor' => 'Visa',
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/iotec/initiate', [
            'type' => 'BUY',
            'media_id' => $movie->id,
            'media_type' => 'MOVIE',
            'method' => 'card',
            'payer_name' => 'John Doe',
            'payer_email' => 'john@example.com',
        ]);

        $response->assertOk();
        $response->assertJsonPath('card_redirect_url', 'https://pay.iotec.io/pegpay/checkout/req-card-123');

        $transaction = PaymentTransaction::where('transaction_ref', $response->json('transaction_ref'))->first();
        $this->assertNotNull($transaction);
        $this->assertSame('IOTEC_CARD', $transaction->provider_code);
        $this->assertSame('PENDING', $transaction->status);
        $this->assertSame('john@example.com', $transaction->meta['payer_email']);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/api/collections/collect')) {
                return false;
            }
            $data = $request->data();

            return $data['payer'] === 'john@example.com'
                && $data['payerName'] === 'John Doe'
                && $data['category'] === 'Card'
                && ! empty($data['redirectUrl']);
        });
    }

    public function test_card_initiate_requires_name_and_email(): void
    {
        $this->makeGateway();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $movie = Movie::factory()->create(['price_buy' => 5000]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/iotec/initiate', [
            'type' => 'BUY',
            'media_id' => $movie->id,
            'media_type' => 'MOVIE',
            'method' => 'card',
        ]);

        $response->assertStatus(422);
    }

    public function test_card_initiate_blocked_when_disabled_via_config(): void
    {
        config(['services.iotec.card_enabled' => false]);
        $this->makeGateway();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $movie = Movie::factory()->create(['price_buy' => 5000]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/iotec/initiate', [
            'type' => 'BUY',
            'media_id' => $movie->id,
            'media_type' => 'MOVIE',
            'method' => 'card',
            'payer_name' => 'John Doe',
            'payer_email' => 'john@example.com',
        ]);

        $response->assertStatus(400);
    }

    public function test_card_initiate_blocked_when_disabled_via_gateway_toggle(): void
    {
        $this->makeGateway(cardEnabled: false);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $movie = Movie::factory()->create(['price_buy' => 5000]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/iotec/initiate', [
            'type' => 'BUY',
            'media_id' => $movie->id,
            'media_type' => 'MOVIE',
            'method' => 'card',
            'payer_name' => 'John Doe',
            'payer_email' => 'john@example.com',
        ]);

        $response->assertStatus(400);
    }

    public function test_mobile_money_still_works_when_gateway_card_toggle_is_off(): void
    {
        $this->makeGateway(cardEnabled: false);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $movie = Movie::factory()->create(['price_buy' => 5000]);

        $this->fakeIotecHttp(['id' => 'req-momo-456', 'status' => 'Pending']);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/iotec/initiate', [
            'type' => 'BUY',
            'media_id' => $movie->id,
            'media_type' => 'MOVIE',
            'phone' => '0770000000',
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'PENDING');
    }

    public function test_mobile_money_initiate_is_unaffected_by_card_changes(): void
    {
        $this->makeGateway();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $movie = Movie::factory()->create(['price_buy' => 5000]);

        $this->fakeIotecHttp([
            'id' => 'req-momo-123',
            'status' => 'Pending',
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/iotec/initiate', [
            'type' => 'BUY',
            'media_id' => $movie->id,
            'media_type' => 'MOVIE',
            'phone' => '0770000000',
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'PENDING');

        $transaction = PaymentTransaction::where('transaction_ref', $response->json('transaction_ref'))->first();
        $this->assertNotNull($transaction);
        $this->assertSame('IOTEC', $transaction->provider_code);
    }
}
