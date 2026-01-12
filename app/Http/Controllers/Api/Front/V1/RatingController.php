<?php

namespace App\Http\Controllers\Api\front\V1;

use Illuminate\Http\Request;
use App\Models\Rating;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\Back\V1\RatingResource;

class RatingController extends Controller
{
    /**
     * Display a listing of the Ratings.
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
                        $validIncludes = ['user', 'blane']; // Valid relationships
                        $includes = explode(',', $value);
                        foreach ($includes as $include) {
                            if (!in_array($include, $validIncludes)) {
                                $fail('The selected ' . $attribute . ' is invalid.');
                            }
                        }
                    },
                ],
                'sort_by' => 'nullable|string|in:created_at,rating,status',
                'sort_order' => 'nullable|string|in:asc,desc',
                'search' => 'nullable|string',
                'status' => 'nullable|string|in:pending,approved,rejected',
                'blane_id' => 'nullable|integer',
                'user_id' => 'nullable|integer',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        $query = Rating::query();

        $this->applyFilters($request, $query);
        $this->applySearch($request, $query);
        $this->applySorting($request, $query);

        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $query->with($includes);
        }

        $ratings = $query->get();

        return RatingResource::collection($ratings);
    }

    /**
     * Store a newly created Rating.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'blane_id' => 'required|integer|exists:blanes,id',
                'user_id' => 'required|integer|exists:users,id',
                'rating' => 'required|integer|min:1|max:5',
                'comment' => 'nullable|string|max:500',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        try {
            $validatedData['status'] = 'pending';
            $rating = Rating::create($validatedData);
            
            return response()->json([
                'message' => 'Rating created successfully',
                'data' => new RatingResource($rating),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create Rating',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified Rating.
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
                        $validIncludes = ['user', 'blane'];
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

        $query = Rating::query();

        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $query->with($includes);
        }

        $rating = $query->find($id);

        if (!$rating) {
            return response()->json(['message' => 'Rating not found'], 404);
        }

        return new RatingResource($rating);
    }

    /**
     * Update the specified Rating.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, $id): JsonResponse
    {
        $rating = Rating::find($id);

        if (!$rating) {
            return response()->json(['message' => 'Rating not found'], 404);
        }

        try {
            $validatedData = $request->validate([
                'rating' => 'nullable|integer|min:1|max:5',
                'comment' => 'nullable|string|max:500',
                'status' => 'nullable|string|in:pending,approved,rejected',
            ]);

            $rating->update($validatedData);

            return response()->json([
                'message' => 'Rating updated successfully',
                'data' => new RatingResource($rating),
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update Rating',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete the specified Rating.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        $rating = Rating::find($id);

        if (!$rating) {
            return response()->json(['message' => 'Rating not found'], 404);
        }

        try {
            $rating->delete();
            return response()->json(['message' => 'Rating deleted successfully']);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete Rating',
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
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('blane_id')) {
            $query->where('blane_id', $request->input('blane_id'));
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->input('user_id'));
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
            $query->where('comment', 'like', "%$search%")
                ->orWhere('status', 'like', "%$search%");
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

        $allowedSortBy = ['created_at', 'rating', 'status'];
        $allowedSortOrder = ['asc', 'desc'];

        if (in_array($sortBy, $allowedSortBy) && in_array($sortOrder, $allowedSortOrder)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('created_at', 'desc');
        }
    }
}
