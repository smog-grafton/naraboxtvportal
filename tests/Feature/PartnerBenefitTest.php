<?php

namespace Tests\Feature;

use App\Models\Movie;
use App\Models\Partner;
use App\Models\PartnerAttribution;
use App\Models\PartnerBenefit;
use App\Models\PartnerBenefitClaim;
use App\Models\PartnerBenefitType;
use App\Models\PaymentGateway;
use App\Models\PaymentTransaction;
use App\Models\TVShow;
use App\Models\User;
use App\Services\MediaAccessService;
use App\Services\PartnerBenefitService;
use Database\Seeders\PartnerBenefitTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PartnerBenefitTest extends TestCase
{
    use RefreshDatabase;

    private User $viewer;
    private Partner $partner;
    private User $partnerOwner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PartnerBenefitTypeSeeder::class);
        $this->viewer = User::factory()->create();
        $partnerUser = User::factory()->create();
        $this->partnerOwner = $partnerUser;
        $this->partner = Partner::query()->create([
            'user_id' => $partnerUser->id,
            'display_name' => 'MC Stubborn',
            'slug' => 'mc-stubborn',
            'referral_code' => 'MCSTUBBORN',
            'status' => 'active',
            'agreement_start_at' => now()->subDay(),
            'agreement_end_at' => now()->addMonth(),
        ]);
        PartnerAttribution::query()->create([
            'user_id' => $this->viewer->id,
            'partner_id' => $this->partner->id,
            'attribution_method' => 'referral_code',
            'referral_code' => $this->partner->referral_code,
            'attributed_at' => now(),
            'commission_valid_from' => now(),
            'commission_valid_until' => now()->addMonth(),
            'locked_at' => now(),
        ]);
    }

    public function test_first_movie_offer_requires_confirmation_and_is_claimed_only_once(): void
    {
        $benefit = $this->enableBenefit(PartnerBenefitType::FIRST_MOVIE_FREE);
        $movie = Movie::factory()->create(['is_free' => false, 'price_rent' => 1000]);
        $otherMovie = Movie::factory()->create(['is_free' => false, 'price_rent' => 1000]);
        $service = app(PartnerBenefitService::class);

        $offer = $service->firstMovieOffer($this->viewer, $movie);
        $this->assertFalse($offer['claimed']);
        $this->assertStringContainsString('MC Stubborn', $offer['message']);
        $this->assertDatabaseCount('partner_benefit_claims', 0);

        $claim = $service->claimFirstMovie($this->viewer, $movie);
        $this->assertSame('redeemed', $claim->status);
        $this->assertSame(0, (int) data_get($claim->metadata, 'revenue_generated_minor'));
        $this->assertDatabaseCount('payment_transactions', 0);
        $this->assertDatabaseCount('partner_earnings', 0);

        $access = app(MediaAccessService::class)->evaluate($movie, $this->viewer);
        $this->assertTrue($access['has_access']);
        $this->assertSame('PARTNER_BENEFIT', $access['access_type']);

        $this->expectException(ValidationException::class);
        $service->claimFirstMovie($this->viewer, $otherMovie);
    }

    public function test_first_movie_offer_rejects_tv_shows_and_disabled_or_expired_benefits(): void
    {
        $benefit = $this->enableBenefit(PartnerBenefitType::FIRST_MOVIE_FREE);
        $tvShow = TVShow::factory()->create(['is_free' => false, 'is_premium' => true]);
        $access = app(MediaAccessService::class)->evaluate($tvShow, $this->viewer);
        $this->assertArrayNotHasKey('partner_benefit_offer', $access);

        $movie = Movie::factory()->create(['is_free' => false, 'price_rent' => 1000]);
        $benefit->update(['ends_at' => now()->subMinute()]);
        $this->assertNull(app(PartnerBenefitService::class)->firstMovieOffer($this->viewer, $movie));

        $benefit->update(['ends_at' => now()->addDay(), 'enabled' => false]);
        $this->assertNull(app(PartnerBenefitService::class)->firstMovieOffer($this->viewer, $movie));
    }

    public function test_first_movie_offer_is_not_available_to_an_unattributed_user(): void
    {
        $this->enableBenefit(PartnerBenefitType::FIRST_MOVIE_FREE);
        $unattributed = User::factory()->create();
        $movie = Movie::factory()->create(['is_free' => false, 'price_rent' => 1000]);

        $this->assertNull(app(PartnerBenefitService::class)->firstMovieOffer($unattributed, $movie));
    }

    public function test_first_payment_discount_uses_integer_ugx_and_actual_settled_amount(): void
    {
        $this->enableBenefit(PartnerBenefitType::FIRST_PAYMENT_DISCOUNT, [
            'percentage_bps' => 1000,
            'maximum_discount_minor' => 0,
            'minimum_spend_minor' => 0,
            'minimum_payable_minor' => 500,
            'eligible_transaction_types' => ['RENT', 'BUY', 'SUBSCRIPTION'],
        ]);
        $transaction = $this->transaction(8500, 'SUBSCRIPTION');
        $service = app(PartnerBenefitService::class);

        $claim = $service->applyFirstPaymentDiscount($transaction->load('user'));
        $this->assertNotNull($claim);
        $this->assertSame('7650.00', $transaction->fresh()->amount);
        $this->assertSame(850, (int) data_get($transaction->fresh()->meta, 'discount_amount_minor'));

        $transaction->update(['status' => 'SUCCESS']);
        $service->markPaymentBenefitRedeemed($transaction);
        $this->assertSame('redeemed', $claim->fresh()->status);

        $second = $this->transaction(1000, 'RENT');
        $this->assertNull($service->applyFirstPaymentDiscount($second->load('user')));
        $this->assertSame('1000.00', $second->fresh()->amount);
    }

    public function test_discount_honours_maximum_and_eligible_transaction_types(): void
    {
        $this->enableBenefit(PartnerBenefitType::FIRST_PAYMENT_DISCOUNT, [
            'percentage_bps' => 2000,
            'maximum_discount_minor' => 500,
            'minimum_payable_minor' => 500,
            'eligible_transaction_types' => ['SUBSCRIPTION'],
        ]);
        $service = app(PartnerBenefitService::class);

        $rent = $this->transaction(5500, 'RENT');
        $this->assertNull($service->applyFirstPaymentDiscount($rent->load('user')));
        $this->assertSame('5500.00', $rent->fresh()->amount);

        $subscription = $this->transaction(5500, 'SUBSCRIPTION');
        $service->applyFirstPaymentDiscount($subscription->load('user'));
        $this->assertSame('5000.00', $subscription->fresh()->amount);
    }

    public function test_discount_applies_to_an_eligible_movie_payment(): void
    {
        $this->enableBenefit(PartnerBenefitType::FIRST_PAYMENT_DISCOUNT, [
            'percentage_bps' => 1000,
            'eligible_transaction_types' => ['RENT'],
        ]);
        $movie = Movie::factory()->create(['price_rent' => 1000]);
        $transaction = $this->transaction(1000, 'RENT');
        $transaction->update([
            'transactionable_type' => Movie::class,
            'transactionable_id' => $movie->id,
        ]);

        app(PartnerBenefitService::class)->applyFirstPaymentDiscount($transaction->fresh(['user', 'transactionable']));

        $this->assertSame('900.00', $transaction->fresh()->amount);
        $this->assertSame(100, (int) data_get($transaction->fresh()->meta, 'discount_amount_minor'));
    }

    public function test_expired_benefit_and_disabled_partner_cannot_discount_payments(): void
    {
        $benefit = $this->enableBenefit(PartnerBenefitType::FIRST_PAYMENT_DISCOUNT, [
            'percentage_bps' => 1000,
            'eligible_transaction_types' => ['SUBSCRIPTION'],
        ]);
        $benefit->update(['ends_at' => now()->subMinute()]);
        $expiredTransaction = $this->transaction(8500, 'SUBSCRIPTION');

        $this->assertNull(app(PartnerBenefitService::class)->applyFirstPaymentDiscount($expiredTransaction->load('user')));
        $this->assertSame('8500.00', $expiredTransaction->fresh()->amount);

        $benefit->update(['ends_at' => now()->addDay()]);
        $this->partner->update(['status' => 'suspended']);
        $disabledPartnerTransaction = $this->transaction(8500, 'SUBSCRIPTION');

        $this->assertNull(app(PartnerBenefitService::class)->applyFirstPaymentDiscount($disabledPartnerTransaction->load('user')));
        $this->assertSame('8500.00', $disabledPartnerTransaction->fresh()->amount);
    }

    public function test_benefit_type_seeder_is_idempotent_and_disabled_by_default(): void
    {
        $this->seed(PartnerBenefitTypeSeeder::class);
        $this->assertSame(2, PartnerBenefitType::query()->count());
        $this->assertSame(0, PartnerBenefitType::query()->where('globally_enabled', true)->count());
    }

    public function test_partner_can_configure_an_administrator_enabled_offer(): void
    {
        $type = PartnerBenefitType::query()
            ->where('code', PartnerBenefitType::FIRST_PAYMENT_DISCOUNT)
            ->firstOrFail();
        $type->update(['globally_enabled' => true]);
        Sanctum::actingAs($this->partnerOwner);

        $this->withHeader('X-API-KEY', (string) config('api.key'))->putJson('/api/v1/partner/benefits/FIRST_PAYMENT_DISCOUNT', [
            'enabled' => true,
            'percentage_bps' => 1000,
        ])->assertOk()
            ->assertJsonPath('data.benefit.enabled', true)
            ->assertJsonPath('data.benefit.configuration.percentage_bps', 1000);

        $this->assertDatabaseHas('partner_benefits', [
            'partner_id' => $this->partner->id,
            'benefit_type_id' => $type->id,
            'enabled' => true,
        ]);
    }

    public function test_partner_cannot_enable_an_offer_while_its_global_switch_is_off(): void
    {
        Sanctum::actingAs($this->partnerOwner);

        $this->withHeader('X-API-KEY', (string) config('api.key'))->putJson('/api/v1/partner/benefits/FIRST_MOVIE_FREE', [
            'enabled' => true,
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'This audience benefit is not currently available from NaraBox.');
    }

    private function enableBenefit(string $code, array $configuration = []): PartnerBenefit
    {
        $type = PartnerBenefitType::query()->where('code', $code)->firstOrFail();
        $type->update(['globally_enabled' => true]);

        return PartnerBenefit::query()->create([
            'partner_id' => $this->partner->id,
            'benefit_type_id' => $type->id,
            'enabled' => true,
            'starts_at' => now()->subMinute(),
            'ends_at' => now()->addMonth(),
            'configuration' => $configuration,
        ]);
    }

    private function transaction(int $amount, string $type): PaymentTransaction
    {
        $gateway = PaymentGateway::query()->first() ?: PaymentGateway::query()->create([
            'name' => 'Test Gateway',
            'slug' => 'test-gateway',
            'code' => 'TEST',
            'type' => 'AUTOMATIC',
            'display_name' => 'Test Gateway',
            'is_active' => true,
        ]);

        return PaymentTransaction::query()->create([
            'user_id' => $this->viewer->id,
            'payment_gateway_id' => $gateway->id,
            'gateway_code' => $gateway->code,
            'type' => $type,
            'transaction_ref' => 'NBX-TEST-'.str()->random(12),
            'amount' => $amount,
            'status' => 'PENDING',
        ]);
    }
}
