<?php

namespace App\Http\Controllers\Api\Front\V1;

use App\Http\Controllers\Api\BaseController;
use App\Http\Resources\Front\V1\SubcategoryResource;
use App\Models\Subcategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SubcategoryController extends BaseController
{
    /**
     * Display a listing of the subcategories.
     *
     * @OA\Get(
     *     path="/front/v1/subcategories",
     *     tags={"Subcategories"},
     *     summary="Get all subcategories",
     *     description="Retrieve a list of all active subcategories",
     *     operationId="getSubcategories",
     *     @OA\Parameter(
     *         name="include",
     *         in="query",
     *         description="Include related resources (category)",
     *         @OA\Schema(type="string", enum={"category"})
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Sort field",
     *         @OA\Schema(type="string", enum={"created_at", "name"})
     *     ),
     *     @OA\Parameter(
     *         name="sort_order",
     *         in="query",
     *         description="Sort order",
     *         @OA\Schema(type="string", enum={"asc", "desc"})
     *     ),
     *     @OA\Parameter(
     *         name="category_id",
     *         in="query",
     *         description="Filter by category ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search term",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Subcategories retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Subcategory"))
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        // Validate input parameters
        try {
            $request->validate([
                'include' => 'nullable|string|in:category', // Relations to include
                'sort_by' => 'nullable|string|in:created_at,name',
                'sort_order' => 'nullable|string|in:asc,desc',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        $query = Subcategory::query();

        // Always filter to show only active subcategories in frontend
        $query->where('status', 'active');

        $this->applyFilters($request, $query);
        $this->applySearch($request, $query);
        $this->applySorting($request, $query);

        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $query->with($includes);
        }

        $subcategories = $query->get();

        return SubcategoryResource::collection($subcategories);
    }

    protected function applyFilters(Request $request, $query)
    {
        if ($request->has('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }
    }

    protected function applySearch(Request $request, $query)
    {
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                  ->orWhere('description', 'like', "%$search%");
            });
        }
    }

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

    /**
     * Display the specified subcategory.
     *
     * @OA\Get(
     *     path="/front/v1/subcategories/{id}",
     *     tags={"Subcategories"},
     *     summary="Get a specific subcategory",
     *     description="Retrieve a single subcategory by ID",
     *     operationId="getSubcategory",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Subcategory ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="include",
     *         in="query",
     *         description="Include related resources (category)",
     *         @OA\Schema(type="string", enum={"category"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Subcategory retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", ref="#/components/schemas/Subcategory")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Subcategory not found",
     *         @OA\JsonContent(ref="#/components/schemas/NotFoundResponse")
     *     )
     * )
     */
    public function show($id, Request $request)
    {
        try {
            $request->validate([
                'include' => 'nullable|string|in:category',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        $query = Subcategory::query();
    
        // Only show active subcategories in frontend
        $query->where('status', 'active');
    
        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $query->with($includes);
        }
    
        $subcategory = $query->find($id);
    
        if (!$subcategory) {
            return response()->json(['error' => 'Subcategory not found'], 404);
        }
    
        return new SubcategoryResource($subcategory);
    }    
}
