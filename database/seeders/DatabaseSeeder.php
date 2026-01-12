<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {

        // Call the seeders
        $this->call([
            CategoriesTableSeeder::class,
            SubcategoriesTableSeeder::class,
            BlanesTableSeeder::class,
            CitiesTableSeeder::class,
            AddressesTableSeeder::class,
            CouponsTableSeeder::class,
            RatingsTableSeeder::class,
            RoleSeeder::class,
            UserSeeder::class,
            MenuItemsTableSeeder::class,
            BannerTableSeeder::class,
        ]);
    }
}
