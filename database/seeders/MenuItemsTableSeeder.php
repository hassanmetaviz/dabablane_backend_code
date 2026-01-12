<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MenuItemsTableSeeder extends Seeder
{
    public function run(): void
    {
        $menuItems = [
            [
                'label' => 'Accueil',
                'url' => '/',
                'position' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'label' => 'Catalogue des Blanes',
                'url' => '/catalogue',
                'position' => 2,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'label' => 'Réservations',
                'url' => '/reservations',
                'position' => 3,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'label'=>'Ecommerce',
                'url'=>'/ecommerce',
                'position'=>4,
                'is_active'=>true,
                'created_at'=>now(),
                'updated_at'=>now(),

            ],
            [
                'label' => 'Ecommerce Special',
                'url' => '/ecommerce-special',
                'position' => 5,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'label' => 'Coupons',
                'url' => '/coupons',
                'position' => 6,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'label' => 'À propos',
                'url' => '/about',
                'position' => 7,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('menu_items')->insert($menuItems);
    }
}
