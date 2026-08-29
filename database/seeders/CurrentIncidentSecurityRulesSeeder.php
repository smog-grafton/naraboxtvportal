<?php

namespace Database\Seeders;

use App\Models\SecurityRule;
use Illuminate\Database\Seeder;

class CurrentIncidentSecurityRulesSeeder extends Seeder
{
    public function run(): void
    {
        $patterns = [
            'Diego Alvarez' => 'Current incident indicator: configured full-name token set. Do not broaden to either token alone.',
            'Ssemakula John' => 'Current incident indicator: configured full-name token set. Do not broaden to the shared surname.',
        ];

        foreach ($patterns as $pattern => $reason) {
            foreach (['BLOCK_REGISTRATION', 'BLOCK_LOGIN', 'BLOCK_PAYMENT'] as $action) {
                SecurityRule::updateOrCreate(
                    [
                        'name' => "Incident response: {$pattern} ({$action})",
                        'identity_type' => 'DISPLAY_NAME',
                        'matching_mode' => 'TOKEN_SET',
                        'action' => $action,
                    ],
                    [
                        'pattern' => $pattern,
                        'enabled' => true,
                        'risk_level' => 'CRITICAL',
                        'reason' => $reason,
                        'notes' => 'Temporary administrator-managed incident rule. Review and expire after the investigation.',
                        'priority' => 1000,
                    ]
                );
            }
        }
    }
}
