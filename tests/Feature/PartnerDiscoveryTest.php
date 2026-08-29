<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\PartnerAttribution;
use App\Models\PartnerReferralEvent;
use App\Models\User;
use App\Services\PartnerAttributionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeader(config('api.header', 'X-API-KEY'), (string) config('api.key'));
    }

    public function test_initial_results_prioritize_registrations_then_other_performance(): void
    {
        $converted = $this->partner('Creator Connect', 'CONNECT');
        $visited = $this->partner('Movie Clips Uganda', 'CLIPS');
        $this->partner('Inactive Partner', 'OFFLINE', 'suspended');

        PartnerAttribution::query()->create([
            'user_id' => User::factory()->create()->id,
            'partner_id' => $converted->id,
            'attribution_method' => 'registration_selection',
            'referral_code' => $converted->referral_code,
            'attributed_at' => now(),
            'commission_valid_from' => now(),
            'commission_valid_until' => now()->addMonth(),
            'locked_at' => now(),
        ]);

        foreach (range(1, 5) as $index) {
            PartnerReferralEvent::query()->create([
                'partner_id' => $visited->id,
                'event_type' => 'visit',
                'visitor_key' => "visitor-{$index}",
                'referral_code' => $visited->referral_code,
                'occurred_at' => now(),
            ]);
        }

        $this->getJson('/api/v1/partners/discovery?limit=3')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $converted->id)
            ->assertJsonPath('data.0.referral_code', 'CONNECT')
            ->assertJsonMissing(['display_name' => 'Inactive Partner']);
    }

    public function test_results_are_searchable_by_partner_name_or_code(): void
    {
        $this->partner('Nara Film Club', 'NFC256');
        $this->partner('Cinema Updates', 'MOVIESUG');

        $this->getJson('/api/v1/partners/discovery?q=nfc&limit=10')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.display_name', 'Nara Film Club');
    }

    public function test_referral_code_assistance_requires_explicit_confirmation_of_a_near_match(): void
    {
        $partner = $this->partner('MC Stubborn', 'MCSTUBBORN');
        $viewer = User::factory()->create();

        $this->getJson('/api/v1/partners/referral-code-assistance?code=MCSTUBORN')
            ->assertOk()
            ->assertJsonPath('data.exact_match', null)
            ->assertJsonPath('data.suggestions.0.referral_code', 'MCSTUBBORN')
            ->assertJsonPath('data.suggestions.0.display_name', 'MC Stubborn');

        $service = app(PartnerAttributionService::class);
        $this->assertNull($service->attribute($viewer, 'MCSTUBORN', null, 'referral_code'));
        $this->assertDatabaseMissing('partner_attributions', ['user_id' => $viewer->id]);

        $confirmed = $service->attribute($viewer, 'MCSTUBBORN', null, 'referral_code');
        $this->assertSame($partner->id, $confirmed?->partner_id);
    }

    public function test_old_lifetime_activity_does_not_beat_a_recently_trending_partner(): void
    {
        $old = $this->partner('Archive Giant', 'ARCHIVE');
        $recent = $this->partner('Rising This Week', 'RISING');

        foreach (range(1, 5) as $index) {
            PartnerAttribution::query()->create([
                'user_id' => User::factory()->create()->id,
                'partner_id' => $old->id,
                'attribution_method' => 'referral_link',
                'referral_code' => $old->referral_code,
                'attributed_at' => now()->subMonths(6)->addMinutes($index),
                'commission_valid_from' => now()->subMonths(6),
                'commission_valid_until' => now()->subMonths(5),
                'locked_at' => now()->subMonths(6),
            ]);
        }

        PartnerAttribution::query()->create([
            'user_id' => User::factory()->create()->id,
            'partner_id' => $recent->id,
            'attribution_method' => 'referral_link',
            'referral_code' => $recent->referral_code,
            'attributed_at' => now()->subDay(),
            'commission_valid_from' => now()->subDay(),
            'commission_valid_until' => now()->addMonth(),
            'locked_at' => now()->subDay(),
        ]);

        $this->getJson('/api/v1/partners/discovery?limit=2')
            ->assertOk()
            ->assertJsonPath('data.0.id', $recent->id);
    }

    public function test_no_referrer_creates_no_attribution_and_first_attribution_cannot_be_overwritten(): void
    {
        $first = $this->partner('First Referrer', 'FIRSTCODE');
        $second = $this->partner('Second Referrer', 'SECONDCODE');
        $viewer = User::factory()->create();
        $service = app(PartnerAttributionService::class);

        $this->assertNull($service->attribute($viewer, null, null));
        $this->assertDatabaseMissing('partner_attributions', ['user_id' => $viewer->id]);

        $confirmed = $service->attribute($viewer, $first->referral_code, null, 'referral_link');
        $conflicting = $service->attribute($viewer, $second->referral_code, null, 'referral_code');

        $this->assertSame($first->id, $confirmed?->partner_id);
        $this->assertSame($first->id, $conflicting?->partner_id);
        $this->assertDatabaseCount('partner_attributions', 1);
    }

    public function test_referral_link_resolves_and_records_a_deduplicated_visit(): void
    {
        $partner = $this->partner('MC Stubborn', 'MCSTUBBORN');

        $this->getJson('/api/v1/partner/referral/MCSTUBBORN')
            ->assertOk()
            ->assertJsonPath('data.partner.slug', $partner->slug);

        $this->postJson('/api/v1/partner/referral/MCSTUBBORN/visit')->assertOk();
        $this->postJson('/api/v1/partner/referral/MCSTUBBORN/visit')->assertOk();

        $this->assertDatabaseCount('partner_referral_events', 1);
    }

    private function partner(string $name, string $code, string $status = 'active'): Partner
    {
        return Partner::query()->create([
            'user_id' => User::factory()->create()->id,
            'display_name' => $name,
            'slug' => str($name)->slug()->toString(),
            'referral_code' => $code,
            'status' => $status,
            'agreement_start_at' => now()->subDay(),
            'agreement_end_at' => now()->addMonth(),
        ]);
    }
}
