<?php

namespace App\Http\Controllers\Api\Back\V1;

use Illuminate\Http\Request;
use App\Models\ShippingDetail;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\Back\V1\ShippingDetailResource;

class ShippingDetailController extends Controller
{
    /**
     * Display a listing of the Shipping Details.
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
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified Shipping Detail.
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
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified Shipping Detail.
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