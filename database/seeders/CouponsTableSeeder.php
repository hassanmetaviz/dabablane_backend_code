<?php

namespace Database\Seeders;

use App\Models\Coupon;
use App\Models\Category;
use Illuminate\Database\Seeder;

class CouponsTableSeeder extends Seeder
{
    public function run(): void
    {
        $categories = Category::all();

        foreach ($categories as $category) {
            Coupon::create([
                'code' => 'WELCOME' . $category->id,
                'discount' => 10.00,
                'validity' => now()->addMonths(3),
                'max_usage' => 100,
                'description' => 'Welcome discount for ' . $category->name,
                'minPurchase' => 100,
                'categories_id' => $category->id,
            ]);
        }
    }
}
