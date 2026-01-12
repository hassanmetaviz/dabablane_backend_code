<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Electronics',
                'description' => 'Electronic devices and accessories',
                'icon_url' => 'icons/electronics.png',
                'image_url' => 'images/electronics.jpg',
                'slug' => Str::slug('Electronics'),
            ],
            [
                'name' => 'Fashion',
                'description' => 'Clothing and accessories',
                'icon_url' => 'icons/fashion.png',
                'image_url' => 'images/fashion.jpg',
                'slug' => Str::slug('Fashion'),
            ],
            [
                'name' => 'Home & Garden',
                'description' => 'Home decor and garden supplies',
                'icon_url' => 'icons/home.png',
                'image_url' => 'images/home.jpg',
                'slug' => Str::slug('Home & Garden'),
            ],
        ];

        foreach ($categories as $category) {
            Category::create($category);
        }
    }
}
