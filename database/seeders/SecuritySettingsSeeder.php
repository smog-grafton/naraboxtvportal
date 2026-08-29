<?php

namespace Database\Seeders;

use App\Models\SecuritySetting;
use App\Services\RuntimeSecuritySettings;
use Illuminate\Database\Seeder;

class SecuritySettingsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (RuntimeSecuritySettings::DEFAULTS as $key => $enabled) {
            SecuritySetting::firstOrCreate(
                ['key' => $key],
                ['value' => ['enabled' => $enabled], 'description' => str($key)->replace('_', ' ')->title()]
            );
        }
    }
}
