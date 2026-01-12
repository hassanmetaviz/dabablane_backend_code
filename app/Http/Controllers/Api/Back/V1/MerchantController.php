<?php

namespace App\Http\Controllers\Api\Back\V1;

use Illuminate\Http\Request;
use App\Models\Merchant;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\Back\V1\MerchantResource;

class MerchantController extends Controller
{
    /**
     * Display a listing of the Merchants.
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
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified Merchant.
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
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified Merchant.
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