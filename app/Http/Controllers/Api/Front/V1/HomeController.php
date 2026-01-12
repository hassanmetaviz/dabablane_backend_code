<?php

namespace App\Http\Controllers\Api\Front\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Front\V1\BannerResource;
use App\Http\Resources\Front\V1\BlaneResource;
use App\Http\Resources\Front\V1\CategoryResource;
use App\Http\Resources\Front\V1\CityResource;
use App\Http\Resources\Front\V1\MenuItemResource;
use App\Models\Banner;
use App\Models\Blane;
use App\Models\Category;
use App\Models\City;
use App\Models\MenuItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'cities' => CityResource::collection(
                    City::active()->orderBy('name')->select('id', 'name')->get()
                ),
                'categories' => CategoryResource::collection(
                    Category::with(['subcategories' => function($query) {
                        $query->where('status', 'active')
                            ->whereHas('blanes', function($q) {
                                $q->where('status', 'active')
                                  ->where('expiration_date', '>=', now())
                                  ->where('visibility', 'public');
                            })
                            ->select('id', 'category_id', 'name');
                    }])
                    ->where('status', 'active')
                    ->whereHas('blanes', function($q) {
                        $q->where('status', 'active')
                          ->where('expiration_date', '>=', now())
                          ->where('visibility', 'public');
                    })
                    ->orderBy('name')
                    ->select('id', 'name', 'slug', 'image_url')
                    ->get()
                ),
                'new_blanes' => BlaneResource::collection(
                    $this->blaneBaseQuery()
                        ->orderByDesc('created_at')
                        ->limit(9)
                        ->get()
                        ->makeHidden(['available_time_slots', 'available_periods'])
                ),
                'popular_blanes' => BlaneResource::collection(
                    $this->getPopularBlanesByRating()
                ),
                'banner' => optional(Banner::first(), fn ($b) => new BannerResource($b)),
                'menu_items' => MenuItemResource::collection(
                    MenuItem::where('is_active', true)->orderBy('position')->get()
                ),
                'featured_blane' => BlaneResource::collection(
                    $this->blaneBaseQuery()
                        ->featured()
                        ->orderByDesc('updated_at')
                        ->limit(2)  // Changed from 1 to 2 to show both featured blanes
                        ->get()
                        ->makeHidden(['available_time_slots', 'available_periods'])
                ),
            ],
        ]);
    }

    private function blaneBaseQuery()
    {
        return Blane::with('blaneImages:blane_id,image_url')
            ->active()
            ->notExpired()
            ->whereHas('category', function($q) {
                $q->where('status', 'active');
            })
            ->select([
                'id',
                'type',
                'name',
                'description',
                'price_current',
                'price_old',
                'city',
                'slug',
                'start_date',
                'expiration_date',
                'livraison_in_city',
                'advantages',
                'views',
                'created_at',
                'updated_at',
            ]);
    }

    private function getPopularBlanesByRating()
    {
        $blaneIds = Blane::withAvg('ratings', 'rating')
            ->active()
            ->notExpired()
            ->whereHas('category', function($q) {
                $q->where('status', 'active');
            })
            ->orderByDesc('ratings_avg_rating')
            ->limit(9)
            ->pluck('id');

        return Blane::with('blaneImages:blane_id,image_url')
            ->whereIn('id', $blaneIds)
            ->get()
            ->makeHidden(['available_time_slots', 'available_periods'])
            ->sortBy(fn($blane) => array_search($blane->id, $blaneIds->toArray()))
            ->values();
    }
}
