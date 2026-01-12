<?php

namespace App\Services;

use App\Models\Blane;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class BlaneQueryService
{
    /**
     * Apply filters to the query.
     *
     * @param Request $request
     * @param Builder $query
     */
    public function applyFilters(Request $request, $query)
    {
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('city')) {
            $query->where('city', 'like', '%' . $request->input('city') . '%');
        }

        if ($request->has('district')) {
            $query->where('district', $request->input('district'));
        }

        if ($request->has('subdistricts')) {
            $query->where('subdistricts', $request->input('subdistricts'));
        }

        if ($request->has('type')) {
            $type = $request->input('type');
            if ($type === 'order') {
                $query->where('type', 'order');
            } else {
                $query->where('type', $type);
            }
        }

        if ($request->has('ratings')) {
            $rating = $request->input('ratings');
            $query->whereHas('ratings', function ($query) use ($rating) {
                $query->havingRaw('AVG(rating) >= ?', [$rating]);
            });
        }

        if ($request->has('commerce_name')) {
            $commerceName = $request->input('commerce_name');
            $query->where('commerce_name', $commerceName);
        }
    }

    /**
     * Apply search to the query.
     *
     * @param Request $request
     * @param Builder $query
     */
    public function applySearch(Request $request, $query)
    {
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where('name', 'like', "%$search%")->orWhere('description', 'like', "%$search%");
        }
    }

    /**
     * Apply sorting to the query.
     *
     * @param Request $request
     * @param Builder $query
     */
    public function applySorting(Request $request, $query)
    {
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');

        $allowedSortBy = ['created_at', 'name', 'price_current'];
        $allowedSortOrder = ['asc', 'desc'];

        if (in_array($sortBy, $allowedSortBy) && in_array($sortOrder, $allowedSortOrder)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('created_at', 'desc');
        }
    }

    /**
     * Apply search query to the query builder.
     *
     * @param Request $request
     * @param Builder $query
     */
    public function applySearchQuery(Request $request, $query)
    {
        $searchQuery = $request->input('query');
        $searchFields = $request->input('search_fields', ['name', 'description', 'city', 'district', 'subdistricts', 'commerce_name', 'advantages', 'conditions', 'vendor_company']);

        Log::info('Searching blanes', [
            'query' => $searchQuery,
            'search_fields' => $searchFields
        ]);

        $query->where(function ($q) use ($searchQuery, $searchFields) {
            foreach ($searchFields as $field) {
                switch ($field) {
                    case 'name':
                        $q->orWhere('name', 'like', "%{$searchQuery}%");
                        break;
                    case 'description':
                        $q->orWhere('description', 'like', "%{$searchQuery}%");
                        break;
                    case 'city':
                        $q->orWhere('city', 'like', "%{$searchQuery}%");
                        break;
                    case 'district':
                        $q->orWhere('district', 'like', "%{$searchQuery}%");
                        break;
                    case 'subdistricts':
                        $q->orWhere('subdistricts', 'like', "%{$searchQuery}%");
                        break;
                    case 'commerce_name':
                        $q->orWhere('commerce_name', 'like', "%{$searchQuery}%");
                        break;
                    case 'advantages':
                        $q->orWhere('advantages', 'like', "%{$searchQuery}%");
                        break;
                    case 'conditions':
                        $q->orWhere('conditions', 'like', "%{$searchQuery}%");
                        break;
                    case 'vendor_company':
                        $q->orWhereHas('vendor', function ($vendorQuery) use ($searchQuery) {
                            $vendorQuery->where('company_name', 'like', "%{$searchQuery}%");
                        });
                        break;
                }
            }

            $q->orWhereHas('category', function ($categoryQuery) use ($searchQuery) {
                $categoryQuery->where('name', 'like', "%{$searchQuery}%");
            })
                ->orWhereHas('subcategory', function ($subcategoryQuery) use ($searchQuery) {
                    $subcategoryQuery->where('name', 'like', "%{$searchQuery}%");
                })
                ->orWhereHas('vendor', function ($vendorQuery) use ($searchQuery) {
                    $vendorQuery->where('company_name', 'like', "%{$searchQuery}%")
                        ->orWhere('name', 'like', "%{$searchQuery}%");
                });
        });
    }

    /**
     * Apply additional filters to the search query.
     *
     * @param Request $request
     * @param Builder $query
     */
    public function applySearchFilters(Request $request, $query)
    {
        if (!$request->input('include_expired', false)) {
            $query->where('expiration_date', '>=', Carbon::today()->toDateString());
        }

        $query->withActiveVendorOrNoVendor();

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        } else {
            $query->where('status', 'active');
        }

        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->has('city')) {
            $query->where('city', 'like', '%' . $request->input('city') . '%');
        }

        if ($request->has('district')) {
            $query->where('district', 'like', '%' . $request->input('district') . '%');
        }

        if ($request->has('subdistricts')) {
            $query->where('subdistricts', 'like', '%' . $request->input('subdistricts') . '%');
        }

        if ($request->has('min_price')) {
            $query->where('price_current', '>=', $request->input('min_price'));
        }

        if ($request->has('max_price')) {
            $query->where('price_current', '<=', $request->input('max_price'));
        }

        if ($request->has('ratings')) {
            $rating = $request->input('ratings');
            $query->whereHas('ratings', function ($query) use ($rating) {
                $query->select('blane_id')
                    ->groupBy('blane_id')
                    ->havingRaw('AVG(rating) >= ?', [$rating]);
            });
        }

        if ($request->input('filter_inactive_subcategories', false)) {
            $query->where(function ($query) {
                $query->whereNull('subcategories_id')
                    ->orWhereHas('subcategory', function ($q) {
                        $q->where('status', 'active');
                    });
            });
        }

        if ($request->has('is_diamond')) {
            $isDiamond = $request->boolean('is_diamond');
            $query->whereHas('vendor', function ($q) use ($isDiamond) {
                $q->where('isDiamond', $isDiamond);
            });
        }

        $query->where(function ($query) {
            $query->where(function ($q) {
                $q->where('type', 'reservation')
                    ->where('nombre_max_reservation', '>', 0);
            })
                ->orWhere(function ($q) {
                    $q->where('type', 'order')
                        ->where('stock', '>', 0);
                })
                ->orWhereNotIn('type', ['reservation', 'order']);
        });
    }
}






