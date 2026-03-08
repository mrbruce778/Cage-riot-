<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'standard_owner', 'allowed_org_type' => 'standard'],
            ['name' => 'standard_viewer', 'allowed_org_type' => 'standard'],
            ['name' => 'enterprise_admin', 'allowed_org_type' => 'enterprise_parent'],
            ['name' => 'enterprise_ops', 'allowed_org_type' => 'enterprise_parent'],
            ['name' => 'enterprise_legal', 'allowed_org_type' => 'enterprise_parent'],
            ['name' => 'enterprise_finance', 'allowed_org_type' => 'enterprise_parent'],
            ['name' => 'artist_owner', 'allowed_org_type' => 'enterprise_artist'],
            ['name' => 'artist_viewer', 'allowed_org_type' => 'enterprise_artist'],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(
                ['name' => $role['name']], // unique key
                ['allowed_org_type' => $role['allowed_org_type']]
            );
        }
    }
}
