<?php

namespace App\Http\Controllers\Api\Back\V1;

use Illuminate\Http\Request;
use App\Models\Address;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Api\BaseController;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\Back\V1\AddressResource;

/**
 * @OA\Schema(
 *     schema="BackAddress",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="user_id", type="integer", example=1),
 *     @OA\Property(property="city", type="string", example="Casablanca"),
 *     @OA\Property(property="address", type="string", example="123 Main Street"),
 *     @OA\Property(property="zip_code", type="string", example="20000"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class AddressController extends BaseController
{
    /**
     * Display a listing of the Addresses.
     *
     * @OA\Get(
     *     path="/back/v1/addresses",
     *     tags={"Back - Addresses"},
     *     summary="List all addresses",
     *     operationId="backAddressesIndex",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="include", in="query", description="Include relationships (user, city, shippingDetails)", @OA\Schema(type="string")),
     *     @OA\Parameter(name="paginationSize", in="query", @OA\Schema(type="integer", default=10)),
     *     @OA\Parameter(name="sort_by", in="query", @OA\Schema(type="string", enum={"created_at", "city", "zip_code"})),
     *     @OA\Parameter(name="sort_order", in="query", @OA\Schema(type="string", enum={"asc", "desc"})),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="city", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="zip_code", in="query", @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="Addresses retrieved",
     *         @OA\JsonContent(@OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/BackAddress")), @OA\Property(property="links", ref="#/components/schemas/PaginationLinks"), @OA\Property(property="meta", ref="#/components/schemas/PaginationMeta"))
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
                        $validIncludes = ['user', 'city', 'shippingDetails']; // Valid relationships
                        $includes = explode(',', $value);
                        foreach ($includes as $include) {
                            if (!in_array($include, $validIncludes)) {
                                $fail('The selected ' . $attribute . ' is invalid.');
                            }
                        }
                    },
                ],
                'paginationSize' => 'nullable|integer|min:1',
                'sort_by' => 'nullable|string|in:created_at,city,zip_code',
                'sort_order' => 'nullable|string|in:asc,desc',
                'search' => 'nullable|string',
                'city' => 'nullable|string',
                'zip_code' => 'nullable|string',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        $query = Address::query();

        $this->applyFilters($request, $query);
        $this->applySearch($request, $query);
        $this->applySorting($request, $query);

        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $query->with($includes);
        }

        $paginationSize = $request->input('paginationSize', 10);
        $addresses = $query->paginate($paginationSize);

        return AddressResource::collection($addresses);
    }

    /**
     * Display the specified Address.
     *
     * @OA\Get(
     *     path="/back/v1/addresses/{id}",
     *     tags={"Back - Addresses"},
     *     summary="Get a specific address",
     *     operationId="backAddressesShow",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="include", in="query", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Address retrieved", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/BackAddress"))),
     *     @OA\Response(response=400, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
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
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        $query = Address::query();

        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $query->with($includes);
        }

        $address = $query->find($id);

        if (!$address) {
            return response()->json(['message' => 'Address not found'], 404);
        }

        return new AddressResource($address);
    }

    /**
     * Store a newly created Address.
     *
     * @OA\Post(
     *     path="/back/v1/addresses",
     *     tags={"Back - Addresses"},
     *     summary="Create a new address",
     *     operationId="backAddressesStore",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"city", "user_id", "address", "zip_code"},
     *         @OA\Property(property="city", type="string", maxLength=255, example="Casablanca"),
     *         @OA\Property(property="user_id", type="integer", example=1),
     *         @OA\Property(property="address", type="string", maxLength=255, example="123 Main Street"),
     *         @OA\Property(property="zip_code", type="string", maxLength=10, example="20000")
     *     )),
     *     @OA\Response(response=201, description="Address created", @OA\JsonContent(@OA\Property(property="message", type="string"), @OA\Property(property="data", ref="#/components/schemas/BackAddress"))),
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
                'city' => 'required|string|max:255',
                'user_id' => 'required|integer|exists:users,id',
                'address' => 'required|string|max:255',
                'zip_code' => 'required|string|max:10',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        try {
            $address = Address::create($validatedData);
            return response()->json([
                'message' => 'Address created successfully',
                'data' => new AddressResource($address),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create Address',
            ], 500);
        }
    }

    /**
     * Update the specified Address.
     *
     * @OA\Put(
     *     path="/back/v1/addresses/{id}",
     *     tags={"Back - Addresses"},
     *     summary="Update an address",
     *     operationId="backAddressesUpdate",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"city", "user_id", "address", "zip_code"},
     *         @OA\Property(property="city", type="string", maxLength=255),
     *         @OA\Property(property="user_id", type="integer"),
     *         @OA\Property(property="address", type="string", maxLength=255),
     *         @OA\Property(property="zip_code", type="string", maxLength=10)
     *     )),
     *     @OA\Response(response=200, description="Address updated", @OA\JsonContent(@OA\Property(property="message", type="string"), @OA\Property(property="data", ref="#/components/schemas/BackAddress"))),
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
                'city' => 'required|string|max:255',
                'user_id' => 'required|integer|exists:users,id',
                'address' => 'required|string|max:255',
                'zip_code' => 'required|string|max:10',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        $address = Address::find($id);

        if (!$address) {
            return response()->json(['message' => 'Address not found'], 404);
        }

        try {
            $address->update($validatedData);
            return response()->json([
                'message' => 'Address updated successfully',
                'data' => new AddressResource($address),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update Address',
            ], 500);
        }
    }

    /**
     * Remove the specified Address.
     *
     * @OA\Delete(
     *     path="/back/v1/addresses/{id}",
     *     tags={"Back - Addresses"},
     *     summary="Delete an address",
     *     operationId="backAddressesDestroy",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Address deleted"),
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
        $address = Address::find($id);

        if (!$address) {
            return response()->json(['message' => 'Address not found'], 404);
        }

        try {
            $address->delete();
            return response()->json([
                'message' => 'Address deleted successfully',
            ], 204);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete Address',
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
        if ($request->has('city')) {
            $query->where('city', 'like', '%' . $request->input('city') . '%');
        }

        if ($request->has('zip_code')) {
            $query->where('zip_code', 'like', '%' . $request->input('zip_code') . '%');
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
            $query->where('city', 'like', "%$search%")
                ->orWhere('address', 'like', "%$search%")
                ->orWhere('zip_code', 'like', "%$search%");
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

        $allowedSortBy = ['created_at', 'city', 'zip_code'];
        $allowedSortOrder = ['asc', 'desc'];

        if (in_array($sortBy, $allowedSortBy) && in_array($sortOrder, $allowedSortOrder)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('created_at', 'desc');
        }
    }
}