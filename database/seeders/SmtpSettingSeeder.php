<?php

namespace Database\Seeders;

use App\Models\SmtpSetting;
use Illuminate\Database\Seeder;

class SmtpSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        SmtpSetting::updateOrCreate(
            ['id' => 1],
            [
                'mailer' => 'smtp',
                'host' => 'smtp.hostinger.com',
                'port' => 465,
                'username' => 'fellowship@eavisualarts.org',
                'password' => '9898@Morgan21@9898',
                'encryption' => 'ssl',
                'from_address' => 'fellowship@eavisualarts.org',
                'from_name' => 'NaraBox',
                'is_active' => true,
            ]
        );

        $this->command->info('SMTP settings seeded successfully!');
    }
}
