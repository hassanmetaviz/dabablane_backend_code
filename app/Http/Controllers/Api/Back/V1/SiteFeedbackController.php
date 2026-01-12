<?php

namespace App\Http\Controllers\Api\Back\V1;

use Illuminate\Http\Request;
use App\Models\SiteFeedback;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\Back\V1\SiteFeedbackResource;

class SiteFeedbackController extends Controller
{
    /**
     * Display a listing of the Site Feedbacks.
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
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified Site Feedback.
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
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified Site Feedback.
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