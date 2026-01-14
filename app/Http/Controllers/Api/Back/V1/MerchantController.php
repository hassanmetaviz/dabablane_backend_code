<?php

namespace App\Http\Controllers\Api\Back\V1;

use Illuminate\Http\Request;
use App\Models\Merchant;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Api\BaseController;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\Back\V1\MerchantResource;

/**
 * @OA\Schema(
 *     schema="BackMerchant",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="ABC Store"),
 *     @OA\Property(property="email", type="string", format="email", example="store@example.com"),
 *     @OA\Property(property="phone", type="string", example="+212612345678"),
 *     @OA\Property(property="address", type="string", example="123 Main St"),
 *     @OA\Property(property="city", type="string", example="Casablanca"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class MerchantController extends BaseController
{
    /**
     * Display a listing of the Merchants.
     *
     * @OA\Get(
     *     path="/back/v1/merchants",
     *     tags={"Back - Merchants"},
     *     summary="List all merchants",
     *     operationId="backMerchantsIndex",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="include", in="query", description="Include relationships (merchantOffers)", @OA\Schema(type="string")),
     *     @OA\Parameter(name="paginationSize", in="query", @OA\Schema(type="integer", default=10)),
     *     @OA\Parameter(name="sort_by", in="query", @OA\Schema(type="string", enum={"created_at", "name"})),
     *     @OA\Parameter(name="sort_order", in="query", @OA\Schema(type="string", enum={"asc", "desc"})),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="city", in="query", @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="Merchants retrieved",
     *         @OA\JsonContent(@OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/BackMerchant")), @OA\Property(property="links", ref="#/components/schemas/PaginationLinks"), @OA\Property(property="meta", ref="#/components/schemas/PaginationMeta"))
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
                        $validIncludes = ['merchantOffers']; // Valid relationships
                        $includes = explode(',', $value);
                        foreach ($includes as $include) {
                            if (!in_array($include, $validIncludes)) {
                                $fail('The selected ' . $attribute . ' is invalid.');
                            }
                        }
                    },
                ],
                'paginationSize' => 'nullable|integer|min:1',
                'sort_by' => 'nullable|string|in:created_at,name',
                'sort_order' => 'nullable|string|in:asc,desc',
                'search' => 'nullable|string',
                'city' => 'nullable|string',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        $query = Merchant::query();

        $this->applyFilters($request, $query);
        $this->applySearch($request, $query);
        $this->applySorting($request, $query);

        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $query->with($includes);
        }

        $paginationSize = $request->input('paginationSize', 10);
        $merchants = $query->paginate($paginationSize);

        return MerchantResource::collection($merchants);
    }

    /**
     * Display the specified Merchant.
     *
     * @OA\Get(
     *     path="/back/v1/merchants/{id}",
     *     tags={"Back - Merchants"},
     *     summary="Get a specific merchant",
     *     operationId="backMerchantsShow",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="include", in="query", description="Include relationships (merchantOffers)", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Merchant retrieved", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/BackMerchant"))),
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
                    function ($attribute, $value, $fail) {
                        $validIncludes = ['merchantOffers']; // Valid relationships
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

        $query = Merchant::query();

        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $query->with($includes);
        }

        $merchant = $query->find($id);

        if (!$merchant) {
            return response()->json(['message' => 'Merchant not found'], 404);
        }

        return new MerchantResource($merchant);
    }

    /**
     * Store a newly created Merchant.
     *
     * @OA\Post(
     *     path="/back/v1/merchants",
     *     tags={"Back - Merchants"},
     *     summary="Create a new merchant",
     *     operationId="backMerchantsStore",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"name", "email", "phone", "address", "city"},
     *         @OA\Property(property="name", type="string", maxLength=255, example="ABC Store"),
     *         @OA\Property(property="email", type="string", format="email", maxLength=255, example="store@example.com"),
     *         @OA\Property(property="phone", type="string", maxLength=20, example="+212612345678"),
     *         @OA\Property(property="address", type="string", maxLength=255, example="123 Main St"),
     *         @OA\Property(property="city", type="string", maxLength=255, example="Casablanca")
     *     )),
     *     @OA\Response(response=201, description="Merchant created", @OA\JsonContent(@OA\Property(property="message", type="string"), @OA\Property(property="data", ref="#/components/schemas/BackMerchant"))),
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
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:merchants',
                'phone' => 'required|string|max:20',
                'address' => 'required|string|max:255',
                'city' => 'required|string|max:255',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        try {
            $merchant = Merchant::create($validatedData);
            return response()->json([
                'message' => 'Merchant created successfully',
                'data' => new MerchantResource($merchant),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create Merchant',
            ], 500);
        }
    }

    /**
     * Update the specified Merchant.
     *
     * @OA\Put(
     *     path="/back/v1/merchants/{id}",
     *     tags={"Back - Merchants"},
     *     summary="Update a merchant",
     *     operationId="backMerchantsUpdate",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"name", "email", "phone", "address", "city"},
     *         @OA\Property(property="name", type="string", maxLength=255),
     *         @OA\Property(property="email", type="string", format="email", maxLength=255),
     *         @OA\Property(property="phone", type="string", maxLength=20),
     *         @OA\Property(property="address", type="string", maxLength=255),
     *         @OA\Property(property="city", type="string", maxLength=255)
     *     )),
     *     @OA\Response(response=200, description="Merchant updated", @OA\JsonContent(@OA\Property(property="message", type="string"), @OA\Property(property="data", ref="#/components/schemas/BackMerchant"))),
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
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:merchants,email,' . $id,
                'phone' => 'required|string|max:20',
                'address' => 'required|string|max:255',
                'city' => 'required|string|max:255',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        $merchant = Merchant::find($id);

        if (!$merchant) {
            return response()->json(['message' => 'Merchant not found'], 404);
        }

        try {
            $merchant->update($validatedData);
            return response()->json([
                'message' => 'Merchant updated successfully',
                'data' => new MerchantResource($merchant),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update Merchant',
            ], 500);
        }
    }

    /**
     * Remove the specified Merchant.
     *
     * @OA\Delete(
     *     path="/back/v1/merchants/{id}",
     *     tags={"Back - Merchants"},
     *     summary="Delete a merchant",
     *     operationId="backMerchantsDestroy",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Merchant deleted"),
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
        $merchant = Merchant::find($id);

        if (!$merchant) {
            return response()->json(['message' => 'Merchant not found'], 404);
        }

        try {
            $merchant->delete();
            return response()->json([
                'message' => 'Merchant deleted successfully',
            ], 204);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete Merchant',
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
            $query->where('name', 'like', "%$search%")
                ->orWhere('email', 'like', "%$search%")
                ->orWhere('phone', 'like', "%$search%")
                ->orWhere('address', 'like', "%$search%");
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

        $allowedSortBy = ['created_at', 'name'];
        $allowedSortOrder = ['asc', 'desc'];

        if (in_array($sortBy, $allowedSortBy) && in_array($sortOrder, $allowedSortOrder)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('created_at', 'desc');
        }
    }
}