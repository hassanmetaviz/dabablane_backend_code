<?php

namespace Database\Seeders;

use App\Models\Rating;
use App\Models\Blane;
use App\Models\User;
use Illuminate\Database\Seeder;

class RatingsTableSeeder extends Seeder
{
    public function run(): void
    {
        $blanes = Blane::all();
        $users = User::all();

        foreach ($blanes as $blane) {
            // Get a random number of users between 1 and the minimum of (total users, 2)
            $randomUsers = $users->random(min($users->count(), rand(1, 2)));
            
            foreach ($randomUsers as $user) {
                Rating::create([
                    'blane_id' => $blane->id,
                    'user_id' => $user->id,
                    'rating' => rand(3, 5),
                    'comment' => 'Great product! Would recommend.',
                ]);
            }
        }
    }
}
