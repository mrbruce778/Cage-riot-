<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use App\Models\UserRole;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $roles = Role::all();

        foreach ($roles as $role) {

            $user = User::create([
                'name' => $role->name . ' User',
                'email' => $role->name . '@test.com',
                'password' => Hash::make('123456')
            ]);

            UserRole::create([
                'user_id' => $user->id,
                'organization_id' => 1,
                'role_id' => $role->id
            ]);
        }
    }
}
