<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Str;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Daily Access',
                'slug' => 'daily-access',
                'description' => '24-hour access to all premium content',
                'duration_days' => 1,
                'price' => 2000,
                'features' => [
                    ['feature' => 'Access to all premium movies'],
                    ['feature' => 'Access to all premium TV shows'],
                    ['feature' => 'HD quality streaming'],
                    ['feature' => 'Ad-free experience'],
                ],
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Weekly Access',
                'slug' => 'weekly-access',
                'description' => '7-day access to all premium content',
                'duration_days' => 7,
                'price' => 5000,
                'features' => [
                    ['feature' => 'Access to all premium movies'],
                    ['feature' => 'Access to all premium TV shows'],
                    ['feature' => 'HD quality streaming'],
                    ['feature' => 'Ad-free experience'],
                ],
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Monthly Access',
                'slug' => 'monthly-access',
                'description' => '30-day access to all premium content',
                'duration_days' => 30,
                'price' => 8500,
                'features' => [
                    ['feature' => 'Access to all premium movies'],
                    ['feature' => 'Access to all premium TV shows'],
                    ['feature' => 'HD quality streaming'],
                    ['feature' => 'Ad-free experience'],
                ],
                'is_active' => true,
                'sort_order' => 3,
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::updateOrCreate(
                ['slug' => $plan['slug']],
                $plan
            );
        }

        $this->command->info('Subscription plans seeded successfully!');
    }
}
