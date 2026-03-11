<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            CategorySeeder::class,
            GenreSeeder::class,
            VJSeeder::class,
            ActorSeeder::class,
            MovieSeeder::class,
            ArticleSeeder::class,
            PaymentGatewaySeeder::class,
            PawaPayGatewaySeeder::class,
            ManualPaymentGatewaySeeder::class,
            SubscriptionPlanSeeder::class,
            SmtpSettingSeeder::class,
            EmailTemplateSeeder::class,
            HeroSlideSeeder::class,
        ]);

        // Create a test admin user
        $adminRole = \App\Models\Role::where('name', 'admin')->first();
        User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@narabox.tv',
            'role_id' => $adminRole->id ?? null,
            'plan' => 'ELITE',
            'plan_status' => 'ACTIVE',
        ]);
    }
}
