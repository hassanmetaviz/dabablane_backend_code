<?php

namespace App\Http\Controllers\Api\Back\V1;

use Illuminate\Http\Request;
use App\Models\MerchantOffer;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Api\BaseController;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\Back\V1\MerchantOfferResource;

/**
 * @OA\Schema(
 *     schema="BackMerchantOffer",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="merchant_id", type="integer", example=1),
 *     @OA\Property(property="offer_details", type="string", example="20% off all products"),
 *     @OA\Property(property="validity", type="string", format="date", example="2025-12-31"),
 *     @OA\Property(property="merchant", ref="#/components/schemas/BackMerchant"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class MerchantOfferController extends BaseController
{
    /**
     * Display a listing of the Merchant Offers.
     *
     * @OA\Get(
     *     path="/back/v1/merchant-offers",
     *     tags={"Back - Merchant Offers"},
     *     summary="List all merchant offers",
     *     operationId="backMerchantOffersIndex",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="include", in="query", description="Include merchant relationship", @OA\Schema(type="string", enum={"merchant"})),
     *     @OA\Parameter(name="paginationSize", in="query", @OA\Schema(type="integer", default=10)),
     *     @OA\Parameter(name="sort_by", in="query", @OA\Schema(type="string", enum={"created_at", "validity"})),
     *     @OA\Parameter(name="sort_order", in="query", @OA\Schema(type="string", enum={"asc", "desc"})),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="merchant_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Merchant offers retrieved",
     *         @OA\JsonContent(@OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/BackMerchantOffer")), @OA\Property(property="links", ref="#/components/schemas/PaginationLinks"), @OA\Property(property="meta", ref="#/components/schemas/PaginationMeta"))
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
                        $validIncludes = ['merchant']; // Valid relationships
                        $includes = explode(',', $value);
                        foreach ($includes as $include) {
                            if (!in_array($include, $validIncludes)) {
                                $fail('The selected ' . $attribute . ' is invalid.');
                            }
                        }
                    },
                ],
                'paginationSize' => 'nullable|integer|min:1',
                'sort_by' => 'nullable|string|in:created_at,validity',
                'sort_order' => 'nullable|string|in:asc,desc',
                'search' => 'nullable|string',
                'merchant_id' => 'nullable|integer',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        $query = MerchantOffer::query();

        $this->applyFilters($request, $query);
        $this->applySearch($request, $query);
        $this->applySorting($request, $query);

        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $query->with($includes);
        }

        $paginationSize = $request->input('paginationSize', 10);
        $merchantOffers = $query->paginate($paginationSize);

        return MerchantOfferResource::collection($merchantOffers);
    }

    /**
     * Display the specified Merchant Offer.
     *
     * @OA\Get(
     *     path="/back/v1/merchant-offers/{id}",
     *     tags={"Back - Merchant Offers"},
     *     summary="Get a specific merchant offer",
     *     operationId="backMerchantOffersShow",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="include", in="query", @OA\Schema(type="string", enum={"merchant"})),
     *     @OA\Response(response=200, description="Merchant offer retrieved", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/BackMerchantOffer"))),
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
                        $validIncludes = ['merchant']; // Valid relationships
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

        $query = MerchantOffer::query();

        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $query->with($includes);
        }

        $merchantOffer = $query->find($id);

        if (!$merchantOffer) {
            return response()->json(['message' => 'Merchant Offer not found'], 404);
        }

        return new MerchantOfferResource($merchantOffer);
    }

    /**
     * Store a newly created Merchant Offer.
     *
     * @OA\Post(
     *     path="/back/v1/merchant-offers",
     *     tags={"Back - Merchant Offers"},
     *     summary="Create a new merchant offer",
     *     operationId="backMerchantOffersStore",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"merchant_id", "offer_details", "validity"},
     *         @OA\Property(property="merchant_id", type="integer", example=1),
     *         @OA\Property(property="offer_details", type="string", example="20% off all products"),
     *         @OA\Property(property="validity", type="string", format="date", example="2025-12-31")
     *     )),
     *     @OA\Response(response=201, description="Merchant offer created", @OA\JsonContent(@OA\Property(property="message", type="string"), @OA\Property(property="data", ref="#/components/schemas/BackMerchantOffer"))),
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
                'merchant_id' => 'required|integer|exists:merchants,id',
                'offer_details' => 'required|string',
                'validity' => 'required|date',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        try {
            $merchantOffer = MerchantOffer::create($validatedData);
            return response()->json([
                'message' => 'Merchant Offer created successfully',
                'data' => new MerchantOfferResource($merchantOffer),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create Merchant Offer',
            ], 500);
        }
    }

    /**
     * Update the specified Merchant Offer.
     *
     * @OA\Put(
     *     path="/back/v1/merchant-offers/{id}",
     *     tags={"Back - Merchant Offers"},
     *     summary="Update a merchant offer",
     *     operationId="backMerchantOffersUpdate",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"merchant_id", "offer_details", "validity"},
     *         @OA\Property(property="merchant_id", type="integer"),
     *         @OA\Property(property="offer_details", type="string"),
     *         @OA\Property(property="validity", type="string", format="date")
     *     )),
     *     @OA\Response(response=200, description="Merchant offer updated", @OA\JsonContent(@OA\Property(property="message", type="string"), @OA\Property(property="data", ref="#/components/schemas/BackMerchantOffer"))),
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
                'merchant_id' => 'required|integer|exists:merchants,id',
                'offer_details' => 'required|string',
                'validity' => 'required|date',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        $merchantOffer = MerchantOffer::find($id);

        if (!$merchantOffer) {
            return response()->json(['message' => 'Merchant Offer not found'], 404);
        }

        try {
            $merchantOffer->update($validatedData);
            return response()->json([
                'message' => 'Merchant Offer updated successfully',
                'data' => new MerchantOfferResource($merchantOffer),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update Merchant Offer',
            ], 500);
        }
    }

    /**
     * Remove the specified Merchant Offer.
     *
     * @OA\Delete(
     *     path="/back/v1/merchant-offers/{id}",
     *     tags={"Back - Merchant Offers"},
     *     summary="Delete a merchant offer",
     *     operationId="backMerchantOffersDestroy",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Merchant offer deleted"),
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
        $merchantOffer = MerchantOffer::find($id);

        if (!$merchantOffer) {
            return response()->json(['message' => 'Merchant Offer not found'], 404);
        }

        try {
            $merchantOffer->delete();
            return response()->json([
                'message' => 'Merchant Offer deleted successfully',
            ], 204);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete Merchant Offer',
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
        if ($request->has('merchant_id')) {
            $query->where('merchant_id', $request->input('merchant_id'));
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
            $query->where('offer_details', 'like', "%$search%");
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

        $allowedSortBy = ['created_at', 'validity'];
        $allowedSortOrder = ['asc', 'desc'];

        if (in_array($sortBy, $allowedSortBy) && in_array($sortOrder, $allowedSortOrder)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('created_at', 'desc');
        }
    }
}