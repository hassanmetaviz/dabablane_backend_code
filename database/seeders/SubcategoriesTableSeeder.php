<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SubcategoriesTableSeeder extends Seeder
{
    public function run(): void
    {
        $subcategories = [
            // Electronics subcategories
            [
                'category_id' => 1,
                'name' => 'Smartphones',
                'description' => 'Mobile phones and accessories'
            ],
            [
                'category_id' => 1,
                'name' => 'Laptops & Computers',
                'description' => 'Computers and accessories'
            ],
            [
                'category_id' => 1,
                'name' => 'Gaming',
                'description' => 'Gaming consoles and accessories'
            ],
            [
                'category_id' => 1,
                'name' => 'Cameras',
                'description' => 'Photography equipment'
            ],
            
            // Sports & Leisure subcategories
            [
                'category_id' => 2,
                'name' => 'Sports Equipment',
                'description' => 'Professional sports gear'
            ],
            [
                'category_id' => 2,
                'name' => 'Outdoor Activities',
                'description' => 'Outdoor sports and activities'
            ],
            
            // Wellness & Beauty subcategories
            [
                'category_id' => 3,
                'name' => 'Spa Services',
                'description' => 'Relaxation and wellness treatments'
            ],
            [
                'category_id' => 3,
                'name' => 'Fitness Classes',
                'description' => 'Personal training and group classes'
            ],
            
            // Events & Venues subcategories
            [
                'category_id' => 4,
                'name' => 'Wedding Venues',
                'description' => 'Wedding halls and services'
            ],
            [
                'category_id' => 4,
                'name' => 'Conference Rooms',
                'description' => 'Business meeting spaces'
            ],
        ];

        foreach ($subcategories as $subcategory) {
            $subcategory['created_at'] = now();
            $subcategory['updated_at'] = now();
            DB::table('subcategories')->insert($subcategory);
        }
    }
}
