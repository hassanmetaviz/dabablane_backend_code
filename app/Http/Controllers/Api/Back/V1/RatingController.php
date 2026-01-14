<?php

namespace App\Http\Controllers\Api\Back\V1;

use Illuminate\Http\Request;
use App\Models\Rating;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Api\BaseController;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\Back\V1\RatingResource;

/**
 * @OA\Schema(
 *     schema="BackRating",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="blane_id", type="integer", example=1),
 *     @OA\Property(property="user_id", type="integer", example=1),
 *     @OA\Property(property="rating", type="integer", minimum=1, maximum=5, example=5),
 *     @OA\Property(property="comment", type="string", example="Great service!"),
 *     @OA\Property(property="status", type="string", enum={"pending", "approved", "rejected"}, example="approved"),
 *     @OA\Property(property="user", ref="#/components/schemas/User"),
 *     @OA\Property(property="blane", ref="#/components/schemas/BackBlane"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class RatingController extends BaseController
{
    /**
     * Display a listing of the Ratings.
     *
     * @OA\Get(
     *     path="/back/v1/ratings",
     *     tags={"Back - Ratings"},
     *     summary="List all ratings",
     *     operationId="backRatingsIndex",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="include", in="query", description="Include relationships (user, blane)", @OA\Schema(type="string")),
     *     @OA\Parameter(name="sort_by", in="query", @OA\Schema(type="string", enum={"created_at", "rating", "status"})),
     *     @OA\Parameter(name="sort_order", in="query", @OA\Schema(type="string", enum={"asc", "desc"})),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string", enum={"pending", "approved", "rejected"})),
     *     @OA\Parameter(name="blane_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="user_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Ratings retrieved", @OA\JsonContent(@OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/BackRating")))),
     *     @OA\Response(response=400, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse"))
     * )
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
                        $validIncludes = ['user', 'blane'];
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
     * @OA\Post(
     *     path="/back/v1/ratings",
     *     tags={"Back - Ratings"},
     *     summary="Create a new rating",
     *     operationId="backRatingsStore",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"blane_id", "rating"},
     *         @OA\Property(property="blane_id", type="integer", example=1),
     *         @OA\Property(property="user_id", type="integer", example=1),
     *         @OA\Property(property="rating", type="integer", minimum=1, maximum=5, example=5),
     *         @OA\Property(property="comment", type="string", maxLength=500, example="Great service!")
     *     )),
     *     @OA\Response(response=201, description="Rating created", @OA\JsonContent(@OA\Property(property="message", type="string"), @OA\Property(property="data", ref="#/components/schemas/BackRating"))),
     *     @OA\Response(response=400, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")),
     *     @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
     * )
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'blane_id' => 'required|integer|exists:blanes,id',
                'user_id' => 'nullable|integer|exists:users,id',
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
            ], 500);
        }
    }

    /**
     * Display the specified Rating.
     *
     * @OA\Get(
     *     path="/back/v1/ratings/{id}",
     *     tags={"Back - Ratings"},
     *     summary="Get a specific rating",
     *     operationId="backRatingsShow",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="include", in="query", description="Include relationships (user, blane)", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Rating retrieved", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/BackRating"))),
     *     @OA\Response(response=400, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")),
     *     @OA\Response(response=404, description="Not found", @OA\JsonContent(ref="#/components/schemas/NotFoundResponse"))
     * )
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
     * @OA\Put(
     *     path="/back/v1/ratings/{id}",
     *     tags={"Back - Ratings"},
     *     summary="Update a rating",
     *     operationId="backRatingsUpdate",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         @OA\Property(property="rating", type="integer", minimum=1, maximum=5),
     *         @OA\Property(property="comment", type="string", maxLength=500),
     *         @OA\Property(property="status", type="string", enum={"pending", "approved", "rejected"})
     *     )),
     *     @OA\Response(response=200, description="Rating updated", @OA\JsonContent(@OA\Property(property="message", type="string"), @OA\Property(property="data", ref="#/components/schemas/BackRating"))),
     *     @OA\Response(response=400, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")),
     *     @OA\Response(response=404, description="Not found", @OA\JsonContent(ref="#/components/schemas/NotFoundResponse")),
     *     @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
     * )
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
            ], 500);
        }
    }

    /**
     * Delete the specified Rating.
     *
     * @OA\Delete(
     *     path="/back/v1/ratings/{id}",
     *     tags={"Back - Ratings"},
     *     summary="Delete a rating",
     *     operationId="backRatingsDestroy",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Rating deleted", @OA\JsonContent(@OA\Property(property="message", type="string"))),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")),
     *     @OA\Response(response=404, description="Not found", @OA\JsonContent(ref="#/components/schemas/NotFoundResponse")),
     *     @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
     * )
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
