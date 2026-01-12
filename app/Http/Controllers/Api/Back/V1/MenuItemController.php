<?php

namespace App\Http\Controllers\Api\Back\V1;

use Illuminate\Http\Request;
use App\Models\MenuItem;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\Back\V1\MenuItemResource;

class MenuItemController extends Controller
{
    /**
     * Display a listing of the MenuItems.
     *
     * @param Request $request
     */
    public function index(Request $request)
    {
        try {
            $request->validate([
                'paginationSize' => 'nullable|integer|min:1',
                'sort_by' => 'nullable|string|in:created_at,label,position',
                'sort_order' => 'nullable|string|in:asc,desc',
                'search' => 'nullable|string',
                'is_active' => 'nullable|boolean',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        $query = MenuItem::query();

        $this->applyFilters($request, $query);
        $this->applySearch($request, $query);
        $this->applySorting($request, $query);

        $paginationSize = $request->input('paginationSize', 10);
        $menuItems = $query->paginate($paginationSize);

        return MenuItemResource::collection($menuItems);
    }

    /**
     * Display the specified MenuItem.
     *
     * @param int $id
     * @param Request $request
     */
    public function show($id, Request $request)
    {
        $menuItem = MenuItem::find($id);

        if (!$menuItem) {
            return response()->json(['message' => 'MenuItem not found'], 404);
        }

        return new MenuItemResource($menuItem);
    }

    /**
     * Store a newly created MenuItem.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'label' => 'required|string|max:255',
                'url' => 'required|string|max:255',
                'position' => 'required|integer',
                'is_active' => 'required|boolean',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        try {
            $menuItem = MenuItem::create($validatedData);
            return response()->json([
                'message' => 'MenuItem created successfully',
                'data' => new MenuItemResource($menuItem),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create MenuItem',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified MenuItem.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'label' => 'required|string|max:255',
                'url' => 'required|string|max:255',
                'position' => 'required|integer',
                'is_active' => 'required|boolean',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        $menuItem = MenuItem::find($id);

        if (!$menuItem) {
            return response()->json(['message' => 'MenuItem not found'], 404);
        }

        try {
            $menuItem->update($validatedData);
            return response()->json([
                'message' => 'MenuItem updated successfully',
                'data' => new MenuItemResource($menuItem),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update MenuItem',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified MenuItem.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        $menuItem = MenuItem::find($id);

        if (!$menuItem) {
            return response()->json(['message' => 'MenuItem not found'], 404);
        }

        try {
            $menuItem->delete();
            return response()->json([
                'message' => 'MenuItem deleted successfully',
            ], 204);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete MenuItem',
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
            $query->where('label', 'like', "%$search%")
                ->orWhere('url', 'like', "%$search%");
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

        $allowedSortBy = ['created_at', 'label', 'position'];
        $allowedSortOrder = ['asc', 'desc'];

        if (in_array($sortBy, $allowedSortBy) && in_array($sortOrder, $allowedSortOrder)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('created_at', 'desc');
        }
    }
     /**
 * Change the status of the menuItem.
 *
 * @param Request $request
 * @param int $id
 * @return \Illuminate\Http\JsonResponse
 */
public function updateStatus(Request $request, $id)
{

    $MenuItem = MenuItem::find($id);

    if (!$MenuItem) {
        return response()->json(['message' => 'menuItem not found'], 404);
    }
    
    $request->validate([
        'is_active' => 'required|boolean', // Ensure is_active is a boolean
    ]);

    $MenuItem->is_active = $request->input('is_active');
    $MenuItem->save();

    return response()->json(['message' => 'Coupon status updated successfully', 'data' => $MenuItem], 200);
}
}