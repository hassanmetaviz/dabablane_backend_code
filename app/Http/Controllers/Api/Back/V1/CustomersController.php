<?php

namespace App\Http\Controllers\Api\Back\V1;

use Illuminate\Http\Request;
use App\Models\Customers;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\Back\V1\CustomersResource;

class CustomersController extends Controller
{
    /**
     * Display a listing of the Customers.
     *
     * @param Request $request
     */
    public function index(Request $request)
    {
        try {
            $request->validate([
                'paginationSize' => 'nullable|integer|min:1',
                'sort_by' => 'nullable|string|in:created_at,name,email,phone,city',
                'sort_order' => 'nullable|string|in:asc,desc',
                'search' => 'nullable|string',                
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        $query = Customers::query();

        $this->applyFilters($request, $query);
        $this->applySearch($request, $query);
        $this->applySorting($request, $query);

        $paginationSize = $request->input('paginationSize', 10);
        $customers = $query->paginate($paginationSize);

        return CustomersResource::collection($customers);
    }

    /**
     * Display the specified Customers.
     *
     * @param int $id
     * @param Request $request
     */
    public function show($id, Request $request)
    {
        $customers = Customers::find($id);

        if (!$customers) {
            return response()->json(['message' => 'Customer not found'], 404);
        }

        return new CustomersResource($customers);
    }

    /**
     * Store a newly created customers.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'phone' => 'required|string|max:255',
                'address' => 'required|string|max:255',
                'city' => 'required|string|max:255',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        try {
            $customers = Customers::create($validatedData);
            return response()->json([
                'message' => 'Customers created successfully',
                'data' => new CustomersResource($customers),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create Customers',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified customers.
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
                'email' => 'required|email|max:255',
                'phone' => 'required|string|max:255',
                'address' => 'required|string|max:255',
                'city' => 'required|string|max:255',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        $customers = Customers::find($id);

        if (!$customers) {
            return response()->json(['message' => 'Customers not found'], 404);
        }

        try {
            $customers->update($validatedData);
            return response()->json([
                'message' => 'Customers updated successfully',
                'data' => new CustomersResource($customers),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update customer',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified customers.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        $customers = Customers::find($id);

        if (!$customers) {
            return response()->json(['message' => 'Customers not found'], 404);
        }

        try {
            $customers->delete();
            return response()->json([
                'message' => 'Customers deleted successfully',
            ], 204);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete Customers',
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
        if ($request->has('is_active')) {
            $query->where('is_active', $request->input('is_active'));
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
            $query->where('email', 'like', "%$search%")
                ->orWhere('name', 'like', "%$search%");
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

        $allowedSortBy = ['created_at', 'name', 'email', 'phone', 'city'];
        $allowedSortOrder = ['asc', 'desc'];

        if (in_array($sortBy, $allowedSortBy) && in_array($sortOrder, $allowedSortOrder)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('created_at', 'desc');
        }
    }
}