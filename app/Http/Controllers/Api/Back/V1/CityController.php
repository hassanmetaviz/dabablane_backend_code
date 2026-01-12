<?php

namespace App\Http\Controllers\Api\Back\V1;

use Illuminate\Http\Request;
use App\Models\City;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\Back\V1\CityResource;

class CityController extends Controller
{
    /**
     * Display a listing of the Cities.
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
                        $validIncludes = ['blanes', 'merchants', 'addresses'];
                        $includes = explode(',', $value);
                        foreach ($includes as $include) {
                            if (!in_array($include, $validIncludes)) {
                                $fail('The selected ' . $attribute . ' is invalid.');
                            }
                        }
                    },
                ],
                'paginationSize' => 'nullable|integer|min:1',
                'sort_by' => 'nullable|string|in:created_at,name',
                'sort_order' => 'nullable|string|in:asc,desc',
                'search' => 'nullable|string',
                'is_active' => 'nullable|boolean',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        $query = City::query();

        $this->applyFilters($request, $query);
        $this->applySearch($request, $query);
        $this->applySorting($request, $query);

        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $query->with($includes);
        }

        $paginationSize = $request->input('paginationSize', 10);
        $cities = $query->paginate($paginationSize);

        return CityResource::collection($cities);
    }

    /**
     * Display the specified City.
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
                        $validIncludes = ['blanes', 'merchants', 'addresses'];
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

        $query = City::query();

        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $query->with($includes);
        }

        $city = $query->find($id);

        if (!$city) {
            return response()->json(['message' => 'City not found'], 404);
        }

        return new CityResource($city);
    }

    /**
     * Store a newly created City.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'name' => 'required|string|max:255',
                'is_active' => 'required|boolean',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        try {
            $city = City::create($validatedData);
            return response()->json([
                'message' => 'City created successfully',
                'data' => new CityResource($city),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create City',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified City.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'name' => 'required|string|max:255',
                'is_active' => 'required|boolean',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        $city = City::find($id);

        if (!$city) {
            return response()->json(['message' => 'City not found'], 404);
        }

        try {
            $city->update($validatedData);
            return response()->json([
                'message' => 'City updated successfully',
                'data' => new CityResource($city),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update City',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified City.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        $city = City::find($id);

        if (!$city) {
            return response()->json(['message' => 'City not found'], 404);
        }

        try {
            $city->delete();
            return response()->json([
                'message' => 'City deleted successfully',
            ], 204);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete City',
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
        if ($request->has('is_active')) {
            $query->where('is_active', $request->input('is_active'));
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
            $query->where('name', 'like', "%$search%");
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
}