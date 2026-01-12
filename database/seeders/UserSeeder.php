<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    public function run()
    {
        $user1 = User::firstOrCreate(
            ['email' => 'user@dabablane.com'],
            [
                'name' => 'User',
                'password' => Hash::make('password'),
            ]
        );
        $user1->assignRole('admin');

        $user2 = User::firstOrCreate(
            ['email' => 'admin@dabablane.com'],
            [
                'name' => 'Admin1234',
                'password' => Hash::make('Password123'),
            ]
        );
        $user2->assignRole('admin');
    }
}