<?php

namespace App\Http\Controllers\Api\Back\V1;

use Illuminate\Http\Request;
use App\Models\MerchantOffer;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\Back\V1\MerchantOfferResource;

class MerchantOfferController extends Controller
{
    /**
     * Display a listing of the Merchant Offers.
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
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified Merchant Offer.
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
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified Merchant Offer.
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