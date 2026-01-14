<?php

namespace App\Http\Controllers\Api\Front\V1;

use Illuminate\Http\Request;
use App\Models\Address;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Api\BaseController;
use App\Http\Resources\Front\V1\AddressResource;

/**
 * @OA\Schema(
 *     schema="Address",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="user_id", type="integer", example=1),
 *     @OA\Property(property="city", type="string", example="Casablanca"),
 *     @OA\Property(property="address", type="string", example="123 Main Street, Apt 4"),
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
     *     path="/front/v1/addresses",
     *     tags={"Addresses"},
     *     summary="Get user addresses",
     *     description="Retrieve all addresses for the authenticated user",
     *     operationId="getAddresses",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Sort field",
     *         @OA\Schema(type="string", enum={"created_at", "city", "zip_code"})
     *     ),
     *     @OA\Parameter(
     *         name="sort_order",
     *         in="query",
     *         description="Sort order",
     *         @OA\Schema(type="string", enum={"asc", "desc"})
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search term",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="city",
     *         in="query",
     *         description="Filter by city",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="zip_code",
     *         in="query",
     *         description="Filter by zip code",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Addresses retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Address"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")
     *     )
     * )
     *
     * @param Request $request
     */
    public function index(Request $request)
    {
        $request->validate([
            'include' => 'nullable|string',
            'sort_by' => 'nullable|string|in:created_at,city,zip_code',
            'sort_order' => 'nullable|string|in:asc,desc',
            'search' => 'nullable|string',
            'city' => 'nullable|string',
            'zip_code' => 'nullable|string',
        ]);

        $query = Address::where('user_id', auth()->id());

        $this->applyFilters($request, $query);
        $this->applySearch($request, $query);
        $this->applySorting($request, $query);

        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $query->with($includes);
        }
        
        $addresses = $query->get();

        return AddressResource::collection($addresses);
    }

    /**
     * Display the specified Address.
     *
     * @OA\Get(
     *     path="/front/v1/addresses/{id}",
     *     tags={"Addresses"},
     *     summary="Get a specific address",
     *     description="Retrieve a single address by ID",
     *     operationId="getAddress",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Address ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Address retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", ref="#/components/schemas/Address")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Address not found",
     *         @OA\JsonContent(ref="#/components/schemas/NotFoundResponse")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")
     *     )
     * )
     *
     * @param int $id
     * @param Request $request
     */
    public function show($id, Request $request)
    {
        $request->validate([
            'include' => 'nullable|string',
        ]);

        $query = Address::where('user_id', auth()->id());

        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $query->with($includes);
        }

        $address = $query->find($id);

        if (!$address) {
            return $this->notFound('Address not found');
        }

        return new AddressResource($address);
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

    /**
     * Store a newly created Address.
     *
     * @OA\Post(
     *     path="/front/v1/addresses",
     *     tags={"Addresses"},
     *     summary="Create a new address",
     *     description="Create a new address for the user",
     *     operationId="createAddress",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"city", "user_id", "address", "zip_code"},
     *             @OA\Property(property="city", type="string", maxLength=255, example="Casablanca"),
     *             @OA\Property(property="user_id", type="integer", example=1),
     *             @OA\Property(property="address", type="string", maxLength=255, example="123 Main Street, Apt 4"),
     *             @OA\Property(property="zip_code", type="string", maxLength=10, example="20000")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Address created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="code", type="integer", example=201),
     *             @OA\Property(property="message", type="string", example="Address created successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Address")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")
     *     )
     * )
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'city' => 'required|string|max:255',
            'user_id' => 'required|integer|exists:users,id',
            'address' => 'required|string|max:255',
            'zip_code' => 'required|string|max:10',
        ]);

        try {
            $address = Address::create($validatedData);
            return $this->created(new AddressResource($address), 'Address created successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to create Address', [], 500);
        }
    }

    /**
     * Update the specified Address.
     *
     * @OA\Put(
     *     path="/front/v1/addresses/{id}",
     *     tags={"Addresses"},
     *     summary="Update an address",
     *     description="Update an existing address",
     *     operationId="updateAddress",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Address ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"city", "user_id", "address", "zip_code"},
     *             @OA\Property(property="city", type="string", maxLength=255, example="Casablanca"),
     *             @OA\Property(property="user_id", type="integer", example=1),
     *             @OA\Property(property="address", type="string", maxLength=255, example="123 Main Street, Apt 4"),
     *             @OA\Property(property="zip_code", type="string", maxLength=10, example="20000")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Address updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="code", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="Address updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Address")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Address not found",
     *         @OA\JsonContent(ref="#/components/schemas/NotFoundResponse")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")
     *     )
     * )
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, $id): JsonResponse
    {
        $validatedData = $request->validate([
            'city' => 'required|string|max:255',
            'user_id' => 'required|integer|exists:users,id',
            'address' => 'required|string|max:255',
            'zip_code' => 'required|string|max:10',
        ]);

        $address = Address::find($id);

        if (!$address) {
            return $this->notFound('Address not found');
        }

        try {
            $address->update($validatedData);
            return $this->success(new AddressResource($address), 'Address updated successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to update Address', [], 500);
        }
    }

    /**
     * Remove the specified Address.
     *
     * @OA\Delete(
     *     path="/front/v1/addresses/{id}",
     *     tags={"Addresses"},
     *     summary="Delete an address",
     *     description="Delete an existing address",
     *     operationId="deleteAddress",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Address ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Address deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="code", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="Address deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Address not found",
     *         @OA\JsonContent(ref="#/components/schemas/NotFoundResponse")
     *     )
     * )
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        $address = Address::find($id);

        if (!$address) {
            return $this->notFound('Address not found');
        }

        try {
            $address->delete();
            return $this->deleted('Address deleted successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to delete Address', [], 500);
        }
    }
}