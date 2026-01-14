<?php

namespace App\Http\Controllers\Api\Back\V1;

use Illuminate\Http\Request;
use App\Models\ShippingDetail;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Api\BaseController;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\Back\V1\ShippingDetailResource;

/**
 * @OA\Schema(
 *     schema="BackShippingDetail",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="order_id", type="integer", example=1),
 *     @OA\Property(property="address_id", type="integer", example=1),
 *     @OA\Property(property="shipping_fee", type="number", format="float", example=25.00),
 *     @OA\Property(property="order", ref="#/components/schemas/BackOrder"),
 *     @OA\Property(property="address", ref="#/components/schemas/BackAddress"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class ShippingDetailController extends BaseController
{
    /**
     * Display a listing of the Shipping Details.
     *
     * @OA\Get(
     *     path="/back/v1/shipping-details",
     *     tags={"Back - Shipping Details"},
     *     summary="List all shipping details",
     *     operationId="backShippingDetailsIndex",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="include", in="query", description="Include relationships", @OA\Schema(type="string", enum={"order", "address"})),
     *     @OA\Parameter(name="paginationSize", in="query", @OA\Schema(type="integer", default=10)),
     *     @OA\Parameter(name="sort_by", in="query", @OA\Schema(type="string", enum={"created_at", "shipping_fee"})),
     *     @OA\Parameter(name="sort_order", in="query", @OA\Schema(type="string", enum={"asc", "desc"})),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="order_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="address_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Shipping details retrieved",
     *         @OA\JsonContent(@OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/BackShippingDetail")), @OA\Property(property="links", ref="#/components/schemas/PaginationLinks"), @OA\Property(property="meta", ref="#/components/schemas/PaginationMeta"))
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
                'include' => [
                    'nullable',
                    'string',
                    function ($attribute, $value, $fail) {
                        $validIncludes = ['order', 'address']; // Valid relationships
                        $includes = explode(',', $value);
                        foreach ($includes as $include) {
                            if (!in_array($include, $validIncludes)) {
                                $fail('The selected ' . $attribute . ' is invalid.');
                            }
                        }
                    },
                ],
                'paginationSize' => 'nullable|integer|min:1',
                'sort_by' => 'nullable|string|in:created_at,shipping_fee',
                'sort_order' => 'nullable|string|in:asc,desc',
                'search' => 'nullable|string',
                'order_id' => 'nullable|integer',
                'address_id' => 'nullable|integer',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        $query = ShippingDetail::query();

        $this->applyFilters($request, $query);
        $this->applySearch($request, $query);
        $this->applySorting($request, $query);

        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $query->with($includes);
        }

        $paginationSize = $request->input('paginationSize', 10);
        $shippingDetails = $query->paginate($paginationSize);

        return ShippingDetailResource::collection($shippingDetails);
    }

    /**
     * Display the specified Shipping Detail.
     *
     * @OA\Get(
     *     path="/back/v1/shipping-details/{id}",
     *     tags={"Back - Shipping Details"},
     *     summary="Get a specific shipping detail",
     *     operationId="backShippingDetailsShow",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="include", in="query", @OA\Schema(type="string", enum={"order", "address"})),
     *     @OA\Response(response=200, description="Shipping detail retrieved", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/BackShippingDetail"))),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")),
     *     @OA\Response(response=404, description="Not found", @OA\JsonContent(ref="#/components/schemas/NotFoundResponse"))
     * )
     *
     * @param int $id
     * @param Request $request
     */
    public function show($id, Request $request)
    {
        try {
            $request->validate([
                'include' => [
                    'nullable',
                    'string',
                    function ($attribute, $value, $fail) {
                        $validIncludes = ['order', 'address']; // Valid relationships
                        $includes = explode(',', $value);
                        foreach ($includes as $include) {
                            if (!in_array($include, $validIncludes)) {
                                $fail('The selected ' . $attribute . ' is invalid.');
                            }
                        }
                    },
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        $query = ShippingDetail::query();

        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $query->with($includes);
        }

        $shippingDetail = $query->find($id);

        if (!$shippingDetail) {
            return response()->json(['message' => 'Shipping Detail not found'], 404);
        }

        return new ShippingDetailResource($shippingDetail);
    }

    /**
     * Store a newly created Shipping Detail.
     *
     * @OA\Post(
     *     path="/back/v1/shipping-details",
     *     tags={"Back - Shipping Details"},
     *     summary="Create a new shipping detail",
     *     operationId="backShippingDetailsStore",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"order_id", "address_id", "shipping_fee"},
     *         @OA\Property(property="order_id", type="integer", example=1),
     *         @OA\Property(property="address_id", type="integer", example=1),
     *         @OA\Property(property="shipping_fee", type="number", format="float", example=25.00)
     *     )),
     *     @OA\Response(response=201, description="Shipping detail created", @OA\JsonContent(@OA\Property(property="message", type="string"), @OA\Property(property="data", ref="#/components/schemas/BackShippingDetail"))),
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
                'order_id' => 'required|integer|exists:orders,id',
                'address_id' => 'required|integer|exists:addresses,id',
                'shipping_fee' => 'required|numeric|min:0',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        try {
            $shippingDetail = ShippingDetail::create($validatedData);
            return response()->json([
                'message' => 'Shipping Detail created successfully',
                'data' => new ShippingDetailResource($shippingDetail),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create Shipping Detail',
            ], 500);
        }
    }

    /**
     * Update the specified Shipping Detail.
     *
     * @OA\Put(
     *     path="/back/v1/shipping-details/{id}",
     *     tags={"Back - Shipping Details"},
     *     summary="Update a shipping detail",
     *     operationId="backShippingDetailsUpdate",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"order_id", "address_id", "shipping_fee"},
     *         @OA\Property(property="order_id", type="integer"),
     *         @OA\Property(property="address_id", type="integer"),
     *         @OA\Property(property="shipping_fee", type="number", format="float")
     *     )),
     *     @OA\Response(response=200, description="Shipping detail updated", @OA\JsonContent(@OA\Property(property="message", type="string"), @OA\Property(property="data", ref="#/components/schemas/BackShippingDetail"))),
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
                'order_id' => 'required|integer|exists:orders,id',
                'address_id' => 'required|integer|exists:addresses,id',
                'shipping_fee' => 'required|numeric|min:0',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        $shippingDetail = ShippingDetail::find($id);

        if (!$shippingDetail) {
            return response()->json(['message' => 'Shipping Detail not found'], 404);
        }

        try {
            $shippingDetail->update($validatedData);
            return response()->json([
                'message' => 'Shipping Detail updated successfully',
                'data' => new ShippingDetailResource($shippingDetail),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update Shipping Detail',
            ], 500);
        }
    }

    /**
     * Remove the specified Shipping Detail.
     *
     * @OA\Delete(
     *     path="/back/v1/shipping-details/{id}",
     *     tags={"Back - Shipping Details"},
     *     summary="Delete a shipping detail",
     *     operationId="backShippingDetailsDestroy",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Shipping detail deleted"),
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
        $shippingDetail = ShippingDetail::find($id);

        if (!$shippingDetail) {
            return response()->json(['message' => 'Shipping Detail not found'], 404);
        }

        try {
            $shippingDetail->delete();
            return response()->json([
                'message' => 'Shipping Detail deleted successfully',
            ], 204);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete Shipping Detail',
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
        if ($request->has('order_id')) {
            $query->where('order_id', $request->input('order_id'));
        }

        if ($request->has('address_id')) {
            $query->where('address_id', $request->input('address_id'));
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
            $query->where('shipping_fee', 'like', "%$search%");
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

        $allowedSortBy = ['created_at', 'shipping_fee'];
        $allowedSortOrder = ['asc', 'desc'];

        if (in_array($sortBy, $allowedSortBy) && in_array($sortOrder, $allowedSortOrder)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('created_at', 'desc');
        }
    }
}