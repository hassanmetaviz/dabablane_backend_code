<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CategoriesTableSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Electronics',
                'description' => 'Latest electronic devices and gadgets',
                'icon_url' => 'icons/electronics.png',
                'image_url' => 'images/electronics.jpg',
            ],
            [
                'name' => 'Sports & Leisure',
                'description' => 'Sports equipment and leisure activities',
                'icon_url' => 'icons/sports.png',
                'image_url' => 'images/sports.jpg',
            ],
            [
                'name' => 'Wellness & Beauty',
                'description' => 'Health, wellness and beauty services',
                'icon_url' => 'icons/wellness.png',
                'image_url' => 'images/wellness.jpg',
            ],
            [
                'name' => 'Events & Venues',
                'description' => 'Event spaces and venue rentals',
                'icon_url' => 'icons/events.png',
                'image_url' => 'images/events.jpg',
            ],
        ];

        foreach ($categories as $category) {
            $category['slug'] = Str::slug($category['name']);
            $category['created_at'] = now();
            $category['updated_at'] = now();
            DB::table('categories')->insert($category);
        }
    }
}
