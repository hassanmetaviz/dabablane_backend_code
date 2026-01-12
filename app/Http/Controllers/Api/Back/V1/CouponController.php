<?php

namespace App\Http\Controllers\Api\Back\V1;

use Illuminate\Http\Request;
use App\Models\Coupon;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\Back\V1\CouponResource;

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
                'paginationSize' => 'nullable|integer|min:1',
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

        $paginationSize = $request->input('paginationSize', 10);
        $coupons = $query->paginate($paginationSize);

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
     * Store a newly created Coupon.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'code' => 'required|string|max:255|unique:coupons,code',
                'discount' => 'required|numeric|min:0',
                'validity' => 'required|date',
                'minPurchase' => 'nullable|numeric|min:0',
                'max_usage' => 'nullable|integer|min:0',
                'description' => 'nullable|string',
                'categories_id' => 'required|exists:categories,id',
                'is_active' => 'required|boolean',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        try {
            $coupon = Coupon::create($validatedData);
            return response()->json([
                'message' => 'Coupon created successfully',
                'data' => new CouponResource($coupon),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create Coupon',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified Coupon.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'code' => 'required|string|max:255',
                'discount' => 'required|numeric|min:0',
                'validity' => 'required|date',
                'minPurchase' => 'nullable|numeric|min:0',
                'max_usage' => 'nullable|integer|min:0',
                'description' => 'nullable|string',
                'categories_id' => 'required|exists:categories,id',
                'is_active' => 'required|boolean',
                'minPurchase' => 'nullable|numeric|min:0',
                'max_usage' => 'nullable|integer|min:0',
                'description' => 'nullable|string',
                'categories_id' => 'required|exists:categories,id',
                'is_active' => 'required|boolean',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        $coupon = Coupon::find($id);

        if (!$coupon) {
            return response()->json(['message' => 'Coupon not found'], 404);
        }

        try {
            $coupon->update($validatedData);
            return response()->json([
                'message' => 'Coupon updated successfully',
                'data' => new CouponResource($coupon),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update Coupon',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified Coupon.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        $coupon = Coupon::find($id);

        if (!$coupon) {
            return response()->json(['message' => 'Coupon not found'], 404);
        }

        try {
            $coupon->delete();
            return response()->json([
                'message' => 'Coupon deleted successfully',
            ], 204);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete Coupon',
                'error' => $e->getMessage(),
            ], 500);
        }
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

    /**
     * Change the status of a coupon.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateStatus(Request $request, $id)
    {

        $coupon = Coupon::find($id);

        if (!$coupon) {
            return response()->json(['message' => 'Coupon not found'], 404);
        }

        $request->validate([
            'is_active' => 'required|boolean', // Ensure is_active is a boolean
        ]);

        $coupon->is_active = $request->input('is_active');
        $coupon->save();

        return response()->json(['message' => 'Coupon status updated successfully', 'data' => $coupon], 200);
    }
}