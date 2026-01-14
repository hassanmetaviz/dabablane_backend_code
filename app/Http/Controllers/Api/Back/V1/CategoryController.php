<?php

namespace App\Http\Controllers\Api\Back\V1;

use App\Http\Controllers\Api\BaseController;
use App\Http\Resources\Back\V1\CategoryResource;
use App\Models\Category;
use App\Models\SubCategory;
use App\Helpers\FileHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * @OA\Schema(
 *     schema="BackCategory",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Electronics"),
 *     @OA\Property(property="description", type="string", example="Electronic devices and gadgets"),
 *     @OA\Property(property="icon_url", type="string", example="categories/icon.png"),
 *     @OA\Property(property="image_url", type="string", example="categories/image.jpg"),
 *     @OA\Property(property="status", type="string", enum={"active", "inactive"}, example="active"),
 *     @OA\Property(property="subcategories", type="array", @OA\Items(ref="#/components/schemas/Subcategory")),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class CategoryController extends BaseController
{
    /**
     * Display a listing of the categories.
     *
     * @OA\Get(
     *     path="/back/v1/categories",
     *     tags={"Back - Categories"},
     *     summary="List all categories",
     *     description="Get a paginated list of categories with optional subcategories include",
     *     operationId="backCategoriesIndex",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="include", in="query", description="Include subcategories", @OA\Schema(type="string", enum={"subcategories"})),
     *     @OA\Parameter(name="paginationSize", in="query", @OA\Schema(type="integer", default=10)),
     *     @OA\Parameter(name="sort_by", in="query", @OA\Schema(type="string", enum={"created_at", "name"})),
     *     @OA\Parameter(name="sort_order", in="query", @OA\Schema(type="string", enum={"asc", "desc"})),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="name", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string", enum={"active", "inactive"})),
     *     @OA\Response(
     *         response=200,
     *         description="Categories retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/BackCategory")),
     *             @OA\Property(property="links", ref="#/components/schemas/PaginationLinks"),
     *             @OA\Property(property="meta", ref="#/components/schemas/PaginationMeta")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse"))
     * )
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $request->validate([
                'include' => 'nullable|string|in:subcategories',
                'paginationSize' => 'nullable|integer|min:1',
                'sort_by' => 'nullable|string|in:created_at,name',
                'sort_order' => 'nullable|string|in:asc,desc',
                'search' => 'nullable|string',
                'name' => 'nullable|string',
                'status' => 'nullable|string|in:active,inactive',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        $query = Category::query();

        $this->applyFilters($request, $query);
        $this->applySearch($request, $query);
        $this->applySorting($request, $query);

        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $query->with($includes);
        }

        $paginationSize = $request->input('paginationSize', 10);
        $categories = $query->paginate($paginationSize);

        return CategoryResource::collection($categories);
    }

    /**
     * Display the specified category.
     *
     * @OA\Get(
     *     path="/back/v1/categories/{id}",
     *     tags={"Back - Categories"},
     *     summary="Get a specific category",
     *     description="Retrieve a single category by ID",
     *     operationId="backCategoriesShow",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="include", in="query", description="Include subcategories", @OA\Schema(type="string", enum={"subcategories"})),
     *     @OA\Response(
     *         response=200,
     *         description="Category retrieved successfully",
     *         @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/BackCategory"))
     *     ),
     *     @OA\Response(response=400, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")),
     *     @OA\Response(response=404, description="Category not found", @OA\JsonContent(ref="#/components/schemas/NotFoundResponse"))
     * )
     *
     * @param int $id
     * @param Request $request
     */
    public function show($id, Request $request)
    {
        try {
            $request->validate([
                'include' => 'nullable|string|in:subcategories',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        $query = Category::query();

        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $query->with($includes);
        }

        $category = $query->findOrFail($id);
        return new CategoryResource($category);
    }

    /**
     * Store a newly created category.
     *
     * @OA\Post(
     *     path="/back/v1/categories",
     *     tags={"Back - Categories"},
     *     summary="Create a new category",
     *     description="Create a new category with optional subcategories and images",
     *     operationId="backCategoriesStore",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"name"},
     *                 @OA\Property(property="name", type="string", maxLength=255, example="Electronics"),
     *                 @OA\Property(property="description", type="string"),
     *                 @OA\Property(property="status", type="string", enum={"active", "inactive"}),
     *                 @OA\Property(property="icon_file", type="string", format="binary"),
     *                 @OA\Property(property="image_file", type="string", format="binary"),
     *                 @OA\Property(property="subcategories", type="array", @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="name", type="string"),
     *                     @OA\Property(property="description", type="string")
     *                 ))
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Category created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Category created successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/BackCategory")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")),
     *     @OA\Response(response=422, description="Validation error or duplicate name", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
     *     @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
     * )
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            if (Category::where('name', $request->input('name'))->exists()) {
                return response()->json([
                    'message' => 'Category name already exists',
                    'errors' => ['name' => ['The category name has already been taken.']]
                ], 422);
            }

            $validatedData = $request->validate([
                'icon_file' => 'nullable|file|mimes:jpeg,png,jpg,gif|max:2048',
                'image_file' => 'nullable|file|mimes:jpeg,png,jpg,gif|max:2048',
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'status' => 'nullable|string|in:active,inactive',
                'subcategories' => 'array',
                'subcategories.*.name' => 'required|string|max:255',
                'subcategories.*.description' => 'nullable|string',
            ]);

            if ($request->hasFile('icon_file')) {
                $uploadResult = FileHelper::uploadFile($request->file('icon_file'), 'categories_images');
                if (isset($uploadResult['error'])) {
                    return response()->json(['error' => 'Icon upload failed'], 422);
                }
                $validatedData['icon_url'] = $uploadResult['file_name'];
            }

            if ($request->hasFile('image_file')) {
                $uploadResult = FileHelper::uploadFile($request->file('image_file'), 'categories_images');
                if (isset($uploadResult['error'])) {
                    return response()->json(['error' => 'Image upload failed'], 422);
                }
                $validatedData['image_url'] = $uploadResult['file_name'];
            }

            $category = Category::create($validatedData);

            if ($request->has('subcategories')) {
                foreach ($request->subcategories as $subCategoryData) {
                    $category->subcategories()->create($subCategoryData);
                }
            }

            return response()->json([
                'message' => 'Category created successfully',
                'data' => new CategoryResource($category->load('subcategories')),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create category',
            ], 500);
        }
    }

    /**
     * Update the specified category.
     *
     * @OA\Put(
     *     path="/back/v1/categories/{id}",
     *     tags={"Back - Categories"},
     *     summary="Update a category",
     *     description="Update a category with its subcategories and images",
     *     operationId="backCategoriesUpdate",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"name"},
     *                 @OA\Property(property="name", type="string", maxLength=255),
     *                 @OA\Property(property="description", type="string"),
     *                 @OA\Property(property="status", type="string", enum={"active", "inactive"}),
     *                 @OA\Property(property="icon_file", type="string", format="binary"),
     *                 @OA\Property(property="image_file", type="string", format="binary"),
     *                 @OA\Property(property="subcategories", type="array", @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="name", type="string"),
     *                     @OA\Property(property="description", type="string")
     *                 ))
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Category updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Category updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/BackCategory")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")),
     *     @OA\Response(response=404, description="Category not found", @OA\JsonContent(ref="#/components/schemas/NotFoundResponse")),
     *     @OA\Response(response=422, description="Duplicate name", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
     *     @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
     * )
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            // Check if category name already exists for other categories
            if (
                Category::where('name', $request->input('name'))
                    ->where('id', '!=', $id)
                    ->exists()
            ) {
                return response()->json([
                    'message' => 'Category name already exists',
                    'errors' => ['name' => ['The category name has already been taken.']]
                ], 422);
            }

            $validatedData = $request->validate([
                'icon_file' => 'nullable|file|mimes:jpeg,png,jpg,gif|max:2048',
                'image_file' => 'nullable|file|mimes:jpeg,png,jpg,gif|max:2048',
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'status' => 'nullable|string|in:active,inactive',
                'subcategories' => 'array',
                'subcategories.*.id' => 'nullable|exists:subcategories,id',
                'subcategories.*.name' => 'required|string|max:255',
                'subcategories.*.description' => 'nullable|string',
            ]);

            try {
                $category = Category::findOrFail($id);

                if ($request->hasFile('icon_file')) {
                    $uploadResult = FileHelper::uploadFile($request->file('icon_file'), 'categories_images');
                    if (isset($uploadResult['error'])) {
                        return response()->json(['error' => 'Icon upload failed'], 422);
                    }
                    $validatedData['icon_url'] = $uploadResult['file_name'];
                }

                if ($request->hasFile('image_file')) {
                    $uploadResult = FileHelper::uploadFile($request->file('image_file'), 'categories_images');
                    if (isset($uploadResult['error'])) {
                        return response()->json(['error' => 'Image upload failed'], 422);
                    }
                    $validatedData['image_url'] = $uploadResult['file_name'];
                }

                $category->update($validatedData);

                if ($request->has('subcategories')) {
                    $existingSubCategoryIds = $category->subcategories()->pluck('id')->toArray();
                    $requestSubCategoryIds = collect($request->input('subcategories', []))
                        ->pluck('id')
                        ->filter()
                        ->toArray();

                    $subCategoryIdsToDelete = array_diff($existingSubCategoryIds, $requestSubCategoryIds);

                    if (!empty($subCategoryIdsToDelete)) {
                        SubCategory::whereIn('id', $subCategoryIdsToDelete)->delete();
                    }

                    foreach ($request->subcategories as $subCategoryData) {
                        if (isset($subCategoryData['id'])) {
                            $subCategory = SubCategory::findOrFail($subCategoryData['id']);
                            $subCategory->update($subCategoryData);
                        } else {
                            $category->subcategories()->create($subCategoryData);
                        }
                    }
                }

                return response()->json([
                    'message' => 'Category updated successfully',
                    'data' => new CategoryResource($category->load('subcategories')),
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'message' => 'Failed to update category',
                ], 500);
            }
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }
    }


    /**
     * Remove the specified category.
     *
     * @OA\Delete(
     *     path="/back/v1/categories/{id}",
     *     tags={"Back - Categories"},
     *     summary="Delete a category",
     *     description="Delete a category and its subcategories",
     *     operationId="backCategoriesDestroy",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Category deleted successfully"),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")),
     *     @OA\Response(response=404, description="Category not found", @OA\JsonContent(ref="#/components/schemas/NotFoundResponse")),
     *     @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
     * )
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            $category = Category::findOrFail($id);
            $category->delete();

            return response()->json([
                'message' => 'Category deleted successfully',
            ], 204);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete category',
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
        if ($request->has('name')) {
            $query->where('name', 'like', '%' . $request->input('name') . '%');
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
            $query->orderBy('created_at', 'desc');
        }
    }

    /**
     * Update the status of a category.
     *
     * @OA\Patch(
     *     path="/back/v1/categories/{id}/status",
     *     tags={"Back - Categories"},
     *     summary="Update category status",
     *     description="Change the status of a category (active/inactive)",
     *     operationId="backCategoriesUpdateStatus",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"status"},
     *             @OA\Property(property="status", type="string", enum={"active", "inactive"}, example="active")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Category status updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Category status updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/BackCategory")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")),
     *     @OA\Response(response=404, description="Category not found", @OA\JsonContent(ref="#/components/schemas/NotFoundResponse")),
     *     @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
     * )
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        try {
            $category = Category::findOrFail($id);

            $validatedData = $request->validate([
                'status' => 'required|string|in:active,inactive',
            ]);

            $category->status = $validatedData['status'];
            $category->save();

            return response()->json([
                'message' => 'Category status updated successfully',
                'data' => new CategoryResource($category)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update category status',
            ], 500);
        }
    }
}
