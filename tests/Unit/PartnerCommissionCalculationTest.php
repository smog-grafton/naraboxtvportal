<?php

namespace Tests\Unit;

use App\Models\Partner;
use App\Services\MoneyService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PartnerCommissionCalculationTest extends TestCase
{
    #[DataProvider('commissionCases')]
    public function test_partner_percentage_is_applied_once_as_basis_points(
        int $commissionableAmount,
        int $percentage,
        int $expectedCommission
    ): void {
        $partner = new Partner([
            'default_commission_bps' => $percentage * 100,
        ]);

        $this->assertSame($percentage * 100, $partner->rateFor('SUBSCRIPTION'));
        $this->assertSame(
            $expectedCommission,
            MoneyService::share($commissionableAmount, $partner->rateFor('SUBSCRIPTION'))
        );
    }

    public static function commissionCases(): array
    {
        return [
            'UGX 8,500 at 20 percent' => [8500, 20, 1700],
            'UGX 5,500 at 20 percent' => [5500, 20, 1100],
            'UGX 1,000 at 10 percent' => [1000, 10, 100],
        ];
    }
}
