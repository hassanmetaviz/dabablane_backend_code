<?php

namespace App\Http\Controllers\Api\Front\V1;

use Illuminate\Http\Request;
use App\Models\Address;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\Front\V1\AddressResource;

class AddressController extends Controller
{
    /**
     * Display a listing of the Addresses.
     *
     * @param Request $request
     */
    public function index(Request $request)
    {
        try {
            $request->validate([
                'include' => 'nullable|string',
                'sort_by' => 'nullable|string|in:created_at,city,zip_code',
                'sort_order' => 'nullable|string|in:asc,desc',
                'search' => 'nullable|string',
                'city' => 'nullable|string',
                'zip_code' => 'nullable|string',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

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

        $query = Address::where('user_id', auth()->id());

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
                'error' => $e->getMessage(),
            ], 500);
        }
    }

      /**
     * Update the specified Address.
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
                'error' => $e->getMessage(),
            ], 500);
        }
    }

        /**
     * Remove the specified Address.
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
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}