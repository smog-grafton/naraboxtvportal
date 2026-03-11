<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'admin', 'display_name' => 'Administrator', 'description' => 'Full system access'],
            ['name' => 'vj', 'display_name' => 'Video Jockey', 'description' => 'Content translator and narrator'],
            ['name' => 'customer', 'display_name' => 'Customer', 'description' => 'Regular viewer and subscriber'],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role['name']], $role);
        }
    }
}
