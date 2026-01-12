<?php

namespace App\Http\Controllers\Api\Back\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Back\V1\SubcategoryResource;
use App\Models\Subcategory;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SubcategoryController extends Controller
{
    /**
     * Display a listing of the subcategories.
     *
     * @param Request $request
     */
    public function index(Request $request)
    {
        try {
            $request->validate([
                'include' => 'nullable|string|in:category', // Valid relationships
                'paginationSize' => 'nullable|integer|min:1',
                'sort_by' => 'nullable|string|in:created_at,name',
                'sort_order' => 'nullable|string|in:asc,desc',
                'search' => 'nullable|string',
                'category_id' => 'nullable|exists:categories,id',
                'status' => 'nullable|string|in:active,inactive',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        $query = Subcategory::query();

        $this->applyFilters($request, $query);
        $this->applySearch($request, $query);
        $this->applySorting($request, $query);

        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $query->with($includes);
        }

        $paginationSize = $request->input('paginationSize', 10);
        $subcategories = $query->paginate($paginationSize);

        return SubcategoryResource::collection($subcategories);
    }

    /**
     * Display the specified subcategory.
     *
     * @param int $id
     * @param Request $request
     */
    public function show($id, Request $request)
    {
        try {
            $request->validate([
                'include' => 'nullable|string|in:category', // Valid relationships
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        $query = Subcategory::query();

        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $query->with($includes);
        }

        $subcategory = $query->findOrFail($id);

        return new SubcategoryResource($subcategory);
    }

    /**
     * Store a newly created subcategory.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'category_id' => 'required|exists:categories,id',
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'status' => 'nullable|string|in:active,inactive',
            ]);

            // Set default status if not provided
            if (!isset($validatedData['status'])) {
                $validatedData['status'] = 'active';
            }
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        try {
            $subcategory = Subcategory::create($validatedData);

            return response()->json([
                'message' => 'Subcategory created successfully',
                'data' => new SubcategoryResource($subcategory),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create subcategory',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified subcategory.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        try {
            $validatedData = $request->validate([
                'category_id' => 'sometimes|required|exists:categories,id',
                'name' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'status' => 'sometimes|required|string|in:active,inactive',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        try {
            $subcategory = Subcategory::findOrFail($id);
            $subcategory->update($validatedData);

            return response()->json([
                'message' => 'Subcategory updated successfully',
                'data' => new SubcategoryResource($subcategory),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update subcategory',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified subcategory.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            $subcategory = Subcategory::findOrFail($id);
            $subcategory->delete();

            return response()->json([
                'message' => 'Subcategory deleted successfully',
            ], 204);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete subcategory',
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
        if ($request->has('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
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
                ->orWhere('description', 'like', "%$search%");
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
            $query->orderBy('created_at', 'desc'); // Default sort
        }
    }
}