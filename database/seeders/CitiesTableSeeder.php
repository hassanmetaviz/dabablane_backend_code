<?php

namespace Database\Seeders;

use App\Models\City;
use Illuminate\Database\Seeder;

class CitiesTableSeeder extends Seeder
{
    public function run(): void
    {
        $cities = [
            ['name' => 'Casablanca'],
            ['name' => 'Rabat'],
            ['name' => 'Marrakech'],
            ['name' => 'Fes'],
            ['name' => 'Tangier'],
        ];

        foreach ($cities as $city) {
            City::create($city);
        }
    }
}
