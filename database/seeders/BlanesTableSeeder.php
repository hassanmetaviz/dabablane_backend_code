<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class BlanesTableSeeder extends Seeder
{
    private $productNames = [
        'iPhone 14 Pro Max',
        'Samsung Galaxy S23',
        'MacBook Pro M2',
        'Nike Air Max 2023',
        'PlayStation 5',
        'Canon EOS R6',
        'Apple Watch Series 8',
        'AirPods Pro'
    ];

    private $serviceNames = [
        'Professional Photography Session',
        'Luxury Spa Treatment',
        'Wedding Hall Reservation',
        'Conference Room Booking',
        'Tennis Court Session',
        'Private Yoga Class',
        'Swimming Pool Access',
        'Personal Training Session'
    ];

    public function run(): void
    {
        $blanes = [];

        // 10 Product entries (orders)
        for ($i = 0; $i < 10; $i++) {
            $status = ['active', 'inactive', 'expired'][rand(0, 2)];
            $blane = [
                'subcategories_id' => rand(1, 5),
                'categories_id' => rand(1, 3),
                'name' => $this->productNames[rand(0, count($this->productNames) - 1)],
                'description' => "Product description " . ($i + 1),
                'price_current' => rand(500, 5000),
                'price_old' => rand(5001, 6000),
                'advantages' => json_encode(['Official Warranty', '1 Year Service', 'Free Delivery']),
                'conditions' => 'Standard warranty conditions apply',
                'city' => ['Casablanca', 'Rabat', 'Marrakech', 'Tangier'][rand(0, 3)],
                'status' => $status,
                'type' => 'order',
                'reservation_type' => null,
                'online' => (bool)rand(0, 1),
                'partiel' => (bool)rand(0, 1),
                'cash' => (bool)rand(0, 1),
                'on_top' => (bool)rand(0, 1),
                'views' => rand(0, 1000),
                'stock' => $status === 'expired' ? 0 : rand(1, 100),
                'max_orders' => rand(1, 10),
                'livraison_in_city' => rand(20, 50),
                'livraison_out_city' => rand(51, 100),
                'start_date' => $status === 'expired' ? Carbon::now()->subMonths(2) : Carbon::now(),
                'expiration_date' => $status === 'expired' ? Carbon::now()->subMonth() : Carbon::now()->addMonths(1),
                'personnes_prestation' => 1,
                'nombre_max_reservation' => null,
                'max_reservation_par_creneau' => null,
                'tva'=> rand(20,50),
                'partiel_field' => rand(10,50),
                'start_day' => null,
                'end_day' => null,
                'jours_creneaux' => null,
                'dates' => null,
                'heure_debut' => null,
                'heure_fin' => null,
                'intervale_reservation' => null,
            ];
            $blane['slug'] = Str::slug($blane['name'] . '-' . ($i + 1));
            $blane['created_at'] = now();
            $blane['updated_at'] = now();
            $blanes[] = $blane;
        }

        // 10 Service entries (reservations)
        for ($i = 0; $i < 10; $i++) {
            $status = ['active', 'inactive', 'expired'][rand(0, 2)];
            $resType = ['pre-reservation', 'instante'][rand(0, 1)];
            $blane = [
                'subcategories_id' => rand(1, 5),
                'categories_id' => rand(1, 3),
                'name' => $this->serviceNames[rand(0, count($this->serviceNames) - 1)],
                'description' => "Service description " . ($i + 1),
                'price_current' => rand(100, 1000),
                'price_old' => rand(1001, 1500),
                'advantages' => json_encode(['Professional Service', 'Certified Staff', 'Premium Facilities']),
                'conditions' => 'Cancellation policy applies',
                'city' => ['Casablanca', 'Rabat', 'Marrakech', 'Tangier'][rand(0, 3)],
                'status' => $status,
                'type' => 'reservation',
                'reservation_type' => $resType,
                'online' => (bool)rand(0, 1),
                'partiel' => (bool)rand(0, 1),
                'cash' => (bool)rand(0, 1),
                'on_top' => (bool)rand(0, 1),
                'views' => rand(0, 1000),
                'stock' => 1,
                'max_orders' => null,
                'start_day' => Carbon::now(),
                'end_day' => Carbon::now()->addMonths(3),
                'jours_creneaux' => json_encode(['lundi', 'mardi', 'samedi']),
                'dates' => $resType === 'pre-reservation' ? json_encode([
                    Carbon::now()->addDays(1)->format('Y-m-d'),
                    Carbon::now()->addDays(2)->format('Y-m-d'),
                ]) : null,
                'heure_debut' => '09:00:00',
                'heure_fin' => '18:00:00',
                'intervale_reservation' => 60,
                'personnes_prestation' => rand(1, 10),
                'nombre_max_reservation' => rand(10, 50),
                'max_reservation_par_creneau' => rand(1, 5),
                'livraison_in_city' => null,
                'livraison_out_city' => null,
                'start_date' => $status === 'expired' ? Carbon::now()->subMonths(2) : Carbon::now(),
                'expiration_date' => $status === 'expired' ? Carbon::now()->subMonth() : Carbon::now()->addMonths(1),
            ];
            $blane['slug'] = Str::slug($blane['name'] . '-' . ($i + 10));
            $blane['created_at'] = now();
            $blane['updated_at'] = now();
            $blanes[] = $blane;
        }

        // Insert all entries
        foreach ($blanes as $blane) {
            DB::table('blanes')->insert($blane);
        }
    }
}
