<?php

namespace App\Http\Controllers\Api\Front\V1;

use Illuminate\Http\Request;
use App\Models\FAQ;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\Front\V1\FAQResource;

class FAQController extends Controller
{
    /**
     * Display a listing of the FAQs.
     *
     * @param Request $request
     */
    public function index(Request $request)
    {
        try {
            $request->validate([                
                'sort_by' => 'nullable|string|in:created_at,question',
                'sort_order' => 'nullable|string|in:asc,desc',
                'search' => 'nullable|string',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        $query = FAQ::query();

        $this->applyFilters($request, $query);
        $this->applySearch($request, $query);
        $this->applySorting($request, $query);
        
        $faqs = $query->get();

        return FAQResource::collection($faqs);
    }

    /**
     * Display the specified FAQ.
     *
     * @param int $id
     * @param Request $request
     */
    public function show($id, Request $request)
    {
        $faq = FAQ::find($id);

        if (!$faq) {
            return response()->json(['message' => 'FAQ not found'], 404);
        }

        return new FAQResource($faq);
    }

/**
     * Store a newly created FAQ.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'question' => 'required|string|max:255',
                'answer' => 'required|string',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        try {
            $faq = FAQ::create($validatedData);
            return response()->json([
                'message' => 'FAQ created successfully',
                'data' => new FAQResource($faq),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create FAQ',
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
        // No specific filters for FAQs
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
            $query->where('question', 'like', "%$search%")
                ->orWhere('answer', 'like', "%$search%");
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

        $allowedSortBy = ['created_at', 'question'];
        $allowedSortOrder = ['asc', 'desc'];

        if (in_array($sortBy, $allowedSortBy) && in_array($sortOrder, $allowedSortOrder)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('created_at', 'desc');
        }
    }
}