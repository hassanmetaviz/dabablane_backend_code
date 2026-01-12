<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BannerTableSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('banner')->insert([
            'title' => 'Welcome to Our Store',
            'description' => 'Discover amazing products at great prices',
            'image_url' => 'https://dbapi.escalarmedia.com/storage/uploads/blanes_images/1740063570XKF91l4JBw.jpg',
            'link' => '/catalogue',
            'btname1' => 'Shop Now',
            'title2' => 'Special Offers',
            'description2' => 'Check out our latest deals and promotions',
            'image_url2' => '/coupons',
            'btname2' => 'View Details',
            'link2' => 'https://dbapi.escalarmedia.com/storage/uploads/blanes_images/1740063570XKF91l4JBw.jpg',
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
}
