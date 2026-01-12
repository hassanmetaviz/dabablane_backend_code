<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Seeder;

class AddressesTableSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();
        
        foreach ($users as $user) {
            Address::create([
                'user_id' => $user->id,
                'city' => 'Casablanca',
                'address' => 'Rue 123, Quartier Example',
                'zip_code' => '20000',
            ]);
        }
    }
}
