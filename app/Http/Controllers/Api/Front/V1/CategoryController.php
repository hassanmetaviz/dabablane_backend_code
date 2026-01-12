<?php

namespace App\Http\Controllers\Api\Front\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Front\V1\CategoryResource;
use App\Http\Resources\Front\V1\SubcategoryResource;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        // Validate input parameters
        try {
            $request->validate([
                'include' => 'nullable|string|in:subcategories', // Add your valid relations here
                'sort_by' => 'nullable|string|in:created_at,name',
                'sort_order' => 'nullable|string|in:asc,desc',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

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

    public function show($id, Request $request)
    {
        try {
            $request->validate([
                'include' => 'nullable|string|in:subcategories',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }
        
        $query = Category::query()->where('status', 'active');

        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $query->with($includes);
        }

        $category = $query->findOrFail($id);
        return new CategoryResource($category);
    }

    public function subCategories($category_id)
    {
        $category = Category::where('status', 'active')->findOrFail($category_id);
        $subCategories = $category->subCategories;
        return SubcategoryResource::collection($subCategories);
    }
}
