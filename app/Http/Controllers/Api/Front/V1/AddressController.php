<?php

namespace App\Http\Controllers\Api\Front\V1;

use Illuminate\Http\Request;
use App\Models\Address;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Api\BaseController;
use App\Http\Resources\Front\V1\AddressResource;

class AddressController extends BaseController
{
    /**
     * Display a listing of the Addresses.
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