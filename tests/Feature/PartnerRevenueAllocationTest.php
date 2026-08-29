<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\PartnerAttribution;
use App\Models\PartnerCampaign;
use App\Models\PaymentGateway;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\RevenueAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerRevenueAllocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_twenty_percent_basis_points_produces_ugx_1700_from_an_ugx_8500_base(): void
    {
        [$viewer, $partner] = $this->attributedViewer(2000);
        $transaction = $this->subscriptionTransaction($viewer, 8500);

        $allocation = app(RevenueAllocationService::class)->calculate($transaction->load('user'));

        $this->assertSame(8500, $allocation['commissionable_minor']);
        $this->assertSame(8500, $allocation['platform_before_partner_minor']);
        $this->assertSame(2000, $allocation['partner_rate_bps']);
        $this->assertSame(1700, $allocation['partner_minor']);
        $this->assertSame($partner->id, $allocation['partner']->id);
    }

    public function test_discounted_actual_amount_is_the_only_amount_allocated(): void
    {
        [$viewer] = $this->attributedViewer(2000);
        $transaction = $this->subscriptionTransaction($viewer, 7650, [
            'original_amount_minor' => 8500,
            'discount_amount_minor' => 850,
            'actual_amount_minor' => 7650,
        ]);

        $allocation = app(RevenueAllocationService::class)->calculate($transaction->load('user'));

        $this->assertSame(7650, $allocation['gross_minor']);
        $this->assertSame(7650, $allocation['commissionable_minor']);
        $this->assertSame(1530, $allocation['partner_minor']);
    }

    public function test_legacy_human_percentages_are_normalized_once_to_basis_points(): void
    {
        [, $partner] = $this->attributedViewer(20);
        $partner->update(['rate_overrides' => ['RENT' => 20, 'BUY' => 1500]]);
        $campaign = PartnerCampaign::query()->create([
            'partner_id' => $partner->id,
            'name' => 'Legacy Campaign',
            'slug' => 'legacy-campaign',
            'referral_code' => 'LEGACY-CAMPAIGN',
            'commission_bps' => 10,
            'status' => 'active',
        ]);

        $migration = require database_path('migrations/2026_08_29_000100_normalize_legacy_partner_commission_rates.php');
        $migration->up();

        $this->assertSame(2000, $partner->fresh()->default_commission_bps);
        $this->assertSame(2000, $partner->fresh()->rate_overrides['RENT']);
        $this->assertSame(1500, $partner->fresh()->rate_overrides['BUY']);
        $this->assertSame(1000, $campaign->fresh()->commission_bps);
    }

    /** @return array{User, Partner} */
    private function attributedViewer(int $commissionBps): array
    {
        $viewer = User::factory()->create();
        $partner = Partner::query()->create([
            'user_id' => User::factory()->create()->id,
            'display_name' => 'MC Stubborn',
            'slug' => 'mc-stubborn',
            'referral_code' => 'MCSTUBBORN',
            'status' => 'active',
            'default_commission_bps' => $commissionBps,
            'agreement_start_at' => now()->subDay(),
            'agreement_end_at' => now()->addMonth(),
        ]);
        PartnerAttribution::query()->create([
            'user_id' => $viewer->id,
            'partner_id' => $partner->id,
            'attribution_method' => 'referral_code',
            'referral_code' => $partner->referral_code,
            'attributed_at' => now(),
            'commission_valid_from' => now()->subMinute(),
            'commission_valid_until' => now()->addMonth(),
            'locked_at' => now(),
        ]);

        return [$viewer, $partner];
    }

    private function subscriptionTransaction(User $viewer, int $amount, array $meta = []): PaymentTransaction
    {
        $gateway = PaymentGateway::query()->create([
            'name' => 'Test Gateway',
            'slug' => 'test-gateway-'.str()->random(8),
            'code' => 'TEST-'.str()->random(8),
            'type' => 'AUTOMATIC',
            'display_name' => 'Test Gateway',
            'is_active' => true,
        ]);

        return PaymentTransaction::query()->create([
            'user_id' => $viewer->id,
            'payment_gateway_id' => $gateway->id,
            'gateway_code' => $gateway->code,
            'type' => 'SUBSCRIPTION',
            'transaction_ref' => 'NBX-ALLOC-'.str()->random(12),
            'amount' => $amount,
            'status' => 'SUCCESS',
            'meta' => $meta,
        ]);
    }
}
