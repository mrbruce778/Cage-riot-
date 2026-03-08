<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Organization;

class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        // Standard Example Organization
        Organization::updateOrCreate(
            ['name' => 'Independent Artist'],
            [
                'tenant_id' => 1,
                'type' => 'standard',
                'parent_id' => null,
            ]
        );

        // Enterprise Parent Example
        $enterprise = Organization::updateOrCreate(
            ['name' => 'Big Label'],
            [
                'tenant_id' => 1,
                'type' => 'enterprise_parent',
                'parent_id' => null,
            ]
        );

        // Enterprise Artist under Big Label
        Organization::updateOrCreate(
            ['name' => 'Artist Under Big Label'],
            [
                'tenant_id' => 1,
                'type' => 'enterprise_artist',
                'parent_id' => $enterprise->id,
            ]
        );
    }
}
