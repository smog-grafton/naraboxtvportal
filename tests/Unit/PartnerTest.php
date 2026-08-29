<?php

namespace Tests\Unit;

use App\Models\Partner;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class PartnerTest extends TestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_partner_start_date_is_inclusive_for_the_whole_kampala_day(): void
    {
        config(['app.partner_timezone' => 'Africa/Kampala']);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-14 00:30:00', 'Africa/Kampala'));

        $partner = new Partner([
            'status' => 'active',
            // This UTC value is 03:00 on 14 August in Kampala.
            'agreement_start_at' => '2026-08-14 00:00:00',
        ]);

        $this->assertTrue($partner->isActiveAt());
    }

    public function test_partner_end_date_is_inclusive_for_the_whole_kampala_day(): void
    {
        config(['app.partner_timezone' => 'Africa/Kampala']);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-14 23:59:59', 'Africa/Kampala'));

        $partner = new Partner([
            'status' => 'active',
            'agreement_end_at' => '2026-08-14 00:00:00',
        ]);

        $this->assertTrue($partner->isActiveAt());
    }

    public function test_partner_is_not_active_before_the_start_calendar_date(): void
    {
        config(['app.partner_timezone' => 'Africa/Kampala']);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-13 23:59:59', 'Africa/Kampala'));

        $partner = new Partner([
            'status' => 'active',
            'agreement_start_at' => '2026-08-14 00:00:00',
        ]);

        $this->assertFalse($partner->isActiveAt());
    }
}
