<?php

namespace App\Http\Controllers\Api\Front\V1;

use Illuminate\Http\Request;
use App\Models\Coupon;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\Front\V1\CouponResource;

class CouponController extends Controller
{
    /**
     * Display a listing of the Coupons.
     *
     * @param Request $request
     */
    public function index(Request $request)
    {
        try {
            $request->validate([                
                'sort_by' => 'nullable|string|in:created_at,code,discount',
                'sort_order' => 'nullable|string|in:asc,desc',
                'search' => 'nullable|string',
                'is_active' => 'nullable|boolean',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        $query = Coupon::query();

        $this->applyFilters($request, $query);
        $this->applySearch($request, $query);
        $this->applySorting($request, $query);
        
        $coupons = $query->get();

        return CouponResource::collection($coupons);
    }

    /**
     * Display the specified Coupon.
     *
     * @param int $id
     * @param Request $request
     */
    public function show($id, Request $request)
    {
        $coupon = Coupon::find($id);

        if (!$coupon) {
            return response()->json(['message' => 'Coupon not found'], 404);
        }

        return new CouponResource($coupon);
    }
    /**
     * Apply filters to the query.
     *
     * @param Request $request
     * @param $query
     */
    protected function applyFilters(Request $request, $query)
    {
        if ($request->has('is_active')) {
            $query->where('is_active', $request->input('is_active'));
        }
    }

    /**
     * Apply search to the query.
     *
     * @param Request $request
     * @param $query
     */
    protected function applySearch(Request $request, $query)
    {
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where('code', 'like', "%$search%")
                ->orWhere('discount', 'like', "%$search%");
        }
    }

    /**
     * Apply sorting to the query.
     *
     * @param Request $request
     * @param $query
     */
    protected function applySorting(Request $request, $query)
    {
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');

        $allowedSortBy = ['created_at', 'code', 'discount'];
        $allowedSortOrder = ['asc', 'desc'];

        if (in_array($sortBy, $allowedSortBy) && in_array($sortOrder, $allowedSortOrder)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('created_at', 'desc');
        }
    }
}