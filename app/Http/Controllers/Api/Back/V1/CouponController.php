<?php

namespace App\Http\Controllers\Api\Back\V1;

use Illuminate\Http\Request;
use App\Models\Coupon;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Api\BaseController;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\Back\V1\CouponResource;

/**
 * @OA\Schema(
 *     schema="BackCoupon",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="code", type="string", example="SUMMER20"),
 *     @OA\Property(property="discount", type="number", format="float", example=20.00),
 *     @OA\Property(property="validity", type="string", format="date", example="2024-12-31"),
 *     @OA\Property(property="minPurchase", type="number", format="float", example=100.00),
 *     @OA\Property(property="max_usage", type="integer", example=100),
 *     @OA\Property(property="description", type="string", example="Summer discount coupon"),
 *     @OA\Property(property="categories_id", type="integer", example=1),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class CouponController extends BaseController
{
    /**
     * Display a listing of the Coupons.
     *
     * @OA\Get(
     *     path="/back/v1/coupons",
     *     tags={"Back - Coupons"},
     *     summary="List all coupons",
     *     operationId="backCouponsIndex",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="paginationSize", in="query", @OA\Schema(type="integer", default=10)),
     *     @OA\Parameter(name="sort_by", in="query", @OA\Schema(type="string", enum={"created_at", "code", "discount"})),
     *     @OA\Parameter(name="sort_order", in="query", @OA\Schema(type="string", enum={"asc", "desc"})),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="is_active", in="query", @OA\Schema(type="boolean")),
     *     @OA\Response(
     *         response=200,
     *         description="Coupons retrieved",
     *         @OA\JsonContent(@OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/BackCoupon")), @OA\Property(property="links", ref="#/components/schemas/PaginationLinks"), @OA\Property(property="meta", ref="#/components/schemas/PaginationMeta"))
     *     ),
     *     @OA\Response(response=400, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse"))
     * )
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
     * @OA\Get(
     *     path="/back/v1/coupons/{id}",
     *     tags={"Back - Coupons"},
     *     summary="Get a specific coupon",
     *     operationId="backCouponsShow",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Coupon retrieved", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/BackCoupon"))),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")),
     *     @OA\Response(response=404, description="Not found", @OA\JsonContent(ref="#/components/schemas/NotFoundResponse"))
     * )
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
     * @OA\Post(
     *     path="/back/v1/coupons",
     *     tags={"Back - Coupons"},
     *     summary="Create a new coupon",
     *     operationId="backCouponsStore",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"code", "discount", "validity", "categories_id", "is_active"},
     *         @OA\Property(property="code", type="string", maxLength=255, example="SUMMER20"),
     *         @OA\Property(property="discount", type="number", minimum=0, example=20.00),
     *         @OA\Property(property="validity", type="string", format="date", example="2024-12-31"),
     *         @OA\Property(property="minPurchase", type="number", minimum=0, example=100.00),
     *         @OA\Property(property="max_usage", type="integer", minimum=0, example=100),
     *         @OA\Property(property="description", type="string"),
     *         @OA\Property(property="categories_id", type="integer", example=1),
     *         @OA\Property(property="is_active", type="boolean", example=true)
     *     )),
     *     @OA\Response(response=201, description="Coupon created", @OA\JsonContent(@OA\Property(property="message", type="string"), @OA\Property(property="data", ref="#/components/schemas/BackCoupon"))),
     *     @OA\Response(response=400, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")),
     *     @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
     * )
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
            ], 500);
        }
    }

    /**
     * Update the specified Coupon.
     *
     * @OA\Put(
     *     path="/back/v1/coupons/{id}",
     *     tags={"Back - Coupons"},
     *     summary="Update a coupon",
     *     operationId="backCouponsUpdate",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"code", "discount", "validity", "categories_id", "is_active"},
     *         @OA\Property(property="code", type="string", maxLength=255),
     *         @OA\Property(property="discount", type="number", minimum=0),
     *         @OA\Property(property="validity", type="string", format="date"),
     *         @OA\Property(property="minPurchase", type="number", minimum=0),
     *         @OA\Property(property="max_usage", type="integer", minimum=0),
     *         @OA\Property(property="description", type="string"),
     *         @OA\Property(property="categories_id", type="integer"),
     *         @OA\Property(property="is_active", type="boolean")
     *     )),
     *     @OA\Response(response=200, description="Coupon updated", @OA\JsonContent(@OA\Property(property="message", type="string"), @OA\Property(property="data", ref="#/components/schemas/BackCoupon"))),
     *     @OA\Response(response=400, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")),
     *     @OA\Response(response=404, description="Not found", @OA\JsonContent(ref="#/components/schemas/NotFoundResponse")),
     *     @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
     * )
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
            ], 500);
        }
    }

    /**
     * Remove the specified Coupon.
     *
     * @OA\Delete(
     *     path="/back/v1/coupons/{id}",
     *     tags={"Back - Coupons"},
     *     summary="Delete a coupon",
     *     operationId="backCouponsDestroy",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Coupon deleted"),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")),
     *     @OA\Response(response=404, description="Not found", @OA\JsonContent(ref="#/components/schemas/NotFoundResponse")),
     *     @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
     * )
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
     * @OA\Patch(
     *     path="/back/v1/coupons/{id}/status",
     *     tags={"Back - Coupons"},
     *     summary="Update coupon status",
     *     operationId="backCouponsUpdateStatus",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"is_active"},
     *         @OA\Property(property="is_active", type="boolean", example=true)
     *     )),
     *     @OA\Response(response=200, description="Coupon status updated", @OA\JsonContent(@OA\Property(property="message", type="string"), @OA\Property(property="data", ref="#/components/schemas/BackCoupon"))),
     *     @OA\Response(response=400, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")),
     *     @OA\Response(response=404, description="Not found", @OA\JsonContent(ref="#/components/schemas/NotFoundResponse"))
     * )
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