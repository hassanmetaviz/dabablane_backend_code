<?php

namespace App\Http\Controllers\Api\Back\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Back\V1\CategoryResource;
use App\Models\Category;
use App\Models\SubCategory;
use App\Helpers\FileHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CategoryController extends Controller
{
    /**
     * Display a listing of the categories.
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
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified category.
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
                    'error' => $e->getMessage(),
                ], 500);
            }
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }
    }


    /**
     * Remove the specified category.
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
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
