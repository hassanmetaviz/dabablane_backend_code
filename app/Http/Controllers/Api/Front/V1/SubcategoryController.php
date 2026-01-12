<?php

namespace App\Http\Controllers\Api\Front\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Front\V1\SubcategoryResource;
use App\Models\Subcategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SubcategoryController extends Controller
{
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
