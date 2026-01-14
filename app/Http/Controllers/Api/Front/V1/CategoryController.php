<?php

namespace App\Http\Controllers\Api\Front\V1;

use OpenApi\Annotations as OA;
use App\Http\Controllers\Api\BaseController;
use App\Http\Resources\Front\V1\CategoryResource;
use App\Http\Resources\Front\V1\SubcategoryResource;
use App\Models\Category;
use Illuminate\Http\Request;

/**
 * @OA\Schema(
 *     schema="Category",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Electronics"),
 *     @OA\Property(property="slug", type="string", example="electronics"),
 *     @OA\Property(property="description", type="string", example="Electronic devices and accessories"),
 *     @OA\Property(property="image", type="string", example="https://example.com/image.jpg"),
 *     @OA\Property(property="status", type="string", enum={"active", "inactive"}, example="active"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="Subcategory",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Smartphones"),
 *     @OA\Property(property="slug", type="string", example="smartphones"),
 *     @OA\Property(property="category_id", type="integer", example=1),
 *     @OA\Property(property="status", type="string", enum={"active", "inactive"}, example="active")
 * )
 */
class CategoryController extends BaseController
{
    /**
     * @OA\Get(
     *     path="/front/v1/categories",
     *     tags={"Categories"},
     *     summary="Get all categories",
     *     description="Retrieve a list of all active categories with optional subcategories",
     *     operationId="getCategories",
     *     @OA\Parameter(
     *         name="include",
     *         in="query",
     *         description="Include related resources (e.g., subcategories)",
     *         @OA\Schema(type="string", enum={"subcategories"})
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
     *         name="search",
     *         in="query",
     *         description="Search term",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Categories retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Category"))
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $request->validate([
            'include' => 'nullable|string|in:subcategories',
            'sort_by' => 'nullable|string|in:created_at,name',
            'sort_order' => 'nullable|string|in:asc,desc',
        ]);

        $query = Category::query()->where('status', 'active');

        $this->applyFilters($request, $query);
        $this->applySearch($request, $query);
        $this->applySorting($request, $query);

        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $query->with($includes);
        }

        $categories = $query->get();

        return CategoryResource::collection($categories);
    }

    protected function applyFilters(Request $request, $query)
    {
        if ($request->has('name')) {
            $query->where('name', 'like', '%' . $request->input('name') . '%');
        }
    }

    protected function applySearch(Request $request, $query)
    {
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where('name', 'like', "%$search%")->orWhere('description', 'like', "%$search%");
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
     * @OA\Get(
     *     path="/front/v1/categories/{id}",
     *     tags={"Categories"},
     *     summary="Get a specific category",
     *     description="Retrieve a single category by ID with optional subcategories",
     *     operationId="getCategory",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Category ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="include",
     *         in="query",
     *         description="Include related resources",
     *         @OA\Schema(type="string", enum={"subcategories"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Category retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", ref="#/components/schemas/Category")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Category not found",
     *         @OA\JsonContent(ref="#/components/schemas/NotFoundResponse")
     *     )
     * )
     */
    public function show($id, Request $request)
    {
        $request->validate([
            'include' => 'nullable|string|in:subcategories',
        ]);

        $query = Category::query()->where('status', 'active');

        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $query->with($includes);
        }

        $category = $query->find($id);

        if (!$category) {
            return $this->notFound('Category not found');
        }

        return new CategoryResource($category);
    }

    /**
     * @OA\Get(
     *     path="/front/v1/categories/{category_id}/subcategories",
     *     tags={"Categories"},
     *     summary="Get subcategories of a category",
     *     description="Retrieve all subcategories belonging to a specific category",
     *     operationId="getCategorySubcategories",
     *     @OA\Parameter(
     *         name="category_id",
     *         in="path",
     *         required=true,
     *         description="Category ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Subcategories retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Subcategory"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Category not found",
     *         @OA\JsonContent(ref="#/components/schemas/NotFoundResponse")
     *     )
     * )
     */
    public function subCategories($category_id)
    {
        $category = Category::where('status', 'active')->findOrFail($category_id);
        $subCategories = $category->subCategories;
        return SubcategoryResource::collection($subCategories);
    }
}
