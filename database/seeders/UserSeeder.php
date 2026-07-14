<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['username' => 'admin',   'password' => Hash::make('password'), 'name' => 'Admin',   'email' => 'admin@nexora.com'],
            ['username' => 'manager', 'password' => Hash::make('password'), 'name' => 'Manager', 'email' => 'manager@nexora.com'],
            ['username' => 'staff',   'password' => Hash::make('password'), 'name' => 'Staff',   'email' => 'staff@nexora.com'],
        ];

        foreach ($users as $user) {
            User::firstOrCreate(
                ['username' => $user['username']],
                $user
            );
        }
    }
}
