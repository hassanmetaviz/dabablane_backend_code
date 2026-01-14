<?php

namespace App\Http\Controllers\Api\Back\V1;

use Illuminate\Http\Request;
use App\Models\SiteFeedback;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Api\BaseController;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\Back\V1\SiteFeedbackResource;

/**
 * @OA\Schema(
 *     schema="BackSiteFeedback",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="user_id", type="integer", example=1),
 *     @OA\Property(property="feedback", type="string", example="Great service!"),
 *     @OA\Property(property="user", ref="#/components/schemas/BackUser"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class SiteFeedbackController extends BaseController
{
    /**
     * Display a listing of the Site Feedbacks.
     *
     * @OA\Get(
     *     path="/back/v1/site-feedbacks",
     *     tags={"Back - Site Feedbacks"},
     *     summary="List all site feedbacks",
     *     operationId="backSiteFeedbacksIndex",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="include", in="query", @OA\Schema(type="string", enum={"user"})),
     *     @OA\Parameter(name="paginationSize", in="query", @OA\Schema(type="integer", default=10)),
     *     @OA\Parameter(name="sort_by", in="query", @OA\Schema(type="string", enum={"created_at"})),
     *     @OA\Parameter(name="sort_order", in="query", @OA\Schema(type="string", enum={"asc", "desc"})),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="user_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Site feedbacks retrieved",
     *         @OA\JsonContent(@OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/BackSiteFeedback")), @OA\Property(property="links", ref="#/components/schemas/PaginationLinks"), @OA\Property(property="meta", ref="#/components/schemas/PaginationMeta"))
     *     ),
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
                        $validIncludes = ['user']; // Valid relationships
                        $includes = explode(',', $value);
                        foreach ($includes as $include) {
                            if (!in_array($include, $validIncludes)) {
                                $fail('The selected ' . $attribute . ' is invalid.');
                            }
                        }
                    },
                ],
                'paginationSize' => 'nullable|integer|min:1',
                'sort_by' => 'nullable|string|in:created_at',
                'sort_order' => 'nullable|string|in:asc,desc',
                'search' => 'nullable|string',
                'user_id' => 'nullable|integer',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        $query = SiteFeedback::query();

        $this->applyFilters($request, $query);
        $this->applySearch($request, $query);
        $this->applySorting($request, $query);

        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $query->with($includes);
        }

        $paginationSize = $request->input('paginationSize', 10);
        $siteFeedbacks = $query->paginate($paginationSize);

        return SiteFeedbackResource::collection($siteFeedbacks);
    }

    /**
     * Display the specified Site Feedback.
     *
     * @OA\Get(
     *     path="/back/v1/site-feedbacks/{id}",
     *     tags={"Back - Site Feedbacks"},
     *     summary="Get a specific site feedback",
     *     operationId="backSiteFeedbacksShow",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="include", in="query", @OA\Schema(type="string", enum={"user"})),
     *     @OA\Response(response=200, description="Site feedback retrieved", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/BackSiteFeedback"))),
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
                        $validIncludes = ['user']; // Valid relationships
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

        $query = SiteFeedback::query();

        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $query->with($includes);
        }

        $siteFeedback = $query->find($id);

        if (!$siteFeedback) {
            return response()->json(['message' => 'Site Feedback not found'], 404);
        }

        return new SiteFeedbackResource($siteFeedback);
    }

    /**
     * Store a newly created Site Feedback.
     *
     * @OA\Post(
     *     path="/back/v1/site-feedbacks",
     *     tags={"Back - Site Feedbacks"},
     *     summary="Create a new site feedback",
     *     operationId="backSiteFeedbacksStore",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"user_id", "feedback"},
     *         @OA\Property(property="user_id", type="integer", example=1),
     *         @OA\Property(property="feedback", type="string", example="Great service!")
     *     )),
     *     @OA\Response(response=201, description="Site feedback created", @OA\JsonContent(@OA\Property(property="message", type="string"), @OA\Property(property="data", ref="#/components/schemas/BackSiteFeedback"))),
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
                'user_id' => 'required|integer|exists:users,id',
                'feedback' => 'required|string',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        try {
            $siteFeedback = SiteFeedback::create($validatedData);
            return response()->json([
                'message' => 'Site Feedback created successfully',
                'data' => new SiteFeedbackResource($siteFeedback),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create Site Feedback',
            ], 500);
        }
    }

    /**
     * Update the specified Site Feedback.
     *
     * @OA\Put(
     *     path="/back/v1/site-feedbacks/{id}",
     *     tags={"Back - Site Feedbacks"},
     *     summary="Update a site feedback",
     *     operationId="backSiteFeedbacksUpdate",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"user_id", "feedback"},
     *         @OA\Property(property="user_id", type="integer"),
     *         @OA\Property(property="feedback", type="string")
     *     )),
     *     @OA\Response(response=200, description="Site feedback updated", @OA\JsonContent(@OA\Property(property="message", type="string"), @OA\Property(property="data", ref="#/components/schemas/BackSiteFeedback"))),
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
        try {
            $validatedData = $request->validate([
                'user_id' => 'required|integer|exists:users,id',
                'feedback' => 'required|string',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        $siteFeedback = SiteFeedback::find($id);

        if (!$siteFeedback) {
            return response()->json(['message' => 'Site Feedback not found'], 404);
        }

        try {
            $siteFeedback->update($validatedData);
            return response()->json([
                'message' => 'Site Feedback updated successfully',
                'data' => new SiteFeedbackResource($siteFeedback),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update Site Feedback',
            ], 500);
        }
    }

    /**
     * Remove the specified Site Feedback.
     *
     * @OA\Delete(
     *     path="/back/v1/site-feedbacks/{id}",
     *     tags={"Back - Site Feedbacks"},
     *     summary="Delete a site feedback",
     *     operationId="backSiteFeedbacksDestroy",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Site feedback deleted"),
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
        $siteFeedback = SiteFeedback::find($id);

        if (!$siteFeedback) {
            return response()->json(['message' => 'Site Feedback not found'], 404);
        }

        try {
            $siteFeedback->delete();
            return response()->json([
                'message' => 'Site Feedback deleted successfully',
            ], 204);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete Site Feedback',
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
            $query->where('feedback', 'like', "%$search%");
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

        $allowedSortBy = ['created_at'];
        $allowedSortOrder = ['asc', 'desc'];

        if (in_array($sortBy, $allowedSortBy) && in_array($sortOrder, $allowedSortOrder)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('created_at', 'desc');
        }
    }
}