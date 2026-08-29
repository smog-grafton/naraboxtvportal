<?php

namespace Database\Seeders;

use App\Models\PartnerBenefitType;
use Illuminate\Database\Seeder;

class PartnerBenefitTypeSeeder extends Seeder
{
    public function run(): void
    {
        PartnerBenefitType::query()->firstOrCreate(
            ['code' => PartnerBenefitType::FIRST_MOVIE_FREE],
            [
                'name' => 'First Movie Free',
                'description' => 'Allows an attributed viewer to claim one paid movie for playback without creating revenue or commission.',
                'globally_enabled' => false,
                'default_configuration' => [
                    'eligible_content_types' => ['MOVIE'],
                    'funding_rule' => 'platform_promotion_unallocated',
                ],
            ]
        );

        PartnerBenefitType::query()->firstOrCreate(
            ['code' => PartnerBenefitType::FIRST_PAYMENT_DISCOUNT],
            [
                'name' => 'First Payment Discount',
                'description' => 'Discounts the first settled movie or subscription payment. Accounting uses the actual amount collected.',
                'globally_enabled' => false,
                'default_configuration' => [
                    'percentage_bps' => 1000,
                    'maximum_discount_minor' => 0,
                    'minimum_spend_minor' => 0,
                    'minimum_payable_minor' => 500,
                    'eligible_transaction_types' => ['RENT', 'BUY', 'SUBSCRIPTION'],
                ],
            ]
        );
    }
}
