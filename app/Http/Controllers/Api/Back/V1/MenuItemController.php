<?php

namespace App\Http\Controllers\Api\Back\V1;

use Illuminate\Http\Request;
use App\Models\MenuItem;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Api\BaseController;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\Back\V1\MenuItemResource;

/**
 * @OA\Schema(
 *     schema="BackMenuItem",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="label", type="string", example="Home"),
 *     @OA\Property(property="url", type="string", example="/home"),
 *     @OA\Property(property="position", type="integer", example=1),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class MenuItemController extends BaseController
{
    /**
     * Display a listing of the MenuItems.
     *
     * @OA\Get(
     *     path="/back/v1/menu-items",
     *     tags={"Back - Menu Items"},
     *     summary="List all menu items",
     *     operationId="backMenuItemsIndex",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="paginationSize", in="query", @OA\Schema(type="integer", default=10)),
     *     @OA\Parameter(name="sort_by", in="query", @OA\Schema(type="string", enum={"created_at", "label", "position"})),
     *     @OA\Parameter(name="sort_order", in="query", @OA\Schema(type="string", enum={"asc", "desc"})),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="is_active", in="query", @OA\Schema(type="boolean")),
     *     @OA\Response(
     *         response=200,
     *         description="Menu items retrieved",
     *         @OA\JsonContent(@OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/BackMenuItem")), @OA\Property(property="links", ref="#/components/schemas/PaginationLinks"), @OA\Property(property="meta", ref="#/components/schemas/PaginationMeta"))
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
     * @OA\Get(
     *     path="/back/v1/menu-items/{id}",
     *     tags={"Back - Menu Items"},
     *     summary="Get a specific menu item",
     *     operationId="backMenuItemsShow",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Menu item retrieved", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/BackMenuItem"))),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")),
     *     @OA\Response(response=404, description="Not found", @OA\JsonContent(ref="#/components/schemas/NotFoundResponse"))
     * )
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
     * @OA\Post(
     *     path="/back/v1/menu-items",
     *     tags={"Back - Menu Items"},
     *     summary="Create a new menu item",
     *     operationId="backMenuItemsStore",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"label", "url", "position", "is_active"},
     *         @OA\Property(property="label", type="string", maxLength=255, example="Home"),
     *         @OA\Property(property="url", type="string", maxLength=255, example="/home"),
     *         @OA\Property(property="position", type="integer", example=1),
     *         @OA\Property(property="is_active", type="boolean", example=true)
     *     )),
     *     @OA\Response(response=201, description="Menu item created", @OA\JsonContent(@OA\Property(property="message", type="string"), @OA\Property(property="data", ref="#/components/schemas/BackMenuItem"))),
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
            ], 500);
        }
    }

    /**
     * Update the specified MenuItem.
     *
     * @OA\Put(
     *     path="/back/v1/menu-items/{id}",
     *     tags={"Back - Menu Items"},
     *     summary="Update a menu item",
     *     operationId="backMenuItemsUpdate",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"label", "url", "position", "is_active"},
     *         @OA\Property(property="label", type="string", maxLength=255),
     *         @OA\Property(property="url", type="string", maxLength=255),
     *         @OA\Property(property="position", type="integer"),
     *         @OA\Property(property="is_active", type="boolean")
     *     )),
     *     @OA\Response(response=200, description="Menu item updated", @OA\JsonContent(@OA\Property(property="message", type="string"), @OA\Property(property="data", ref="#/components/schemas/BackMenuItem"))),
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
            ], 500);
        }
    }

    /**
     * Remove the specified MenuItem.
     *
     * @OA\Delete(
     *     path="/back/v1/menu-items/{id}",
     *     tags={"Back - Menu Items"},
     *     summary="Delete a menu item",
     *     operationId="backMenuItemsDestroy",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Menu item deleted"),
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
     * @OA\Patch(
     *     path="/back/v1/menu-items/{id}/status",
     *     tags={"Back - Menu Items"},
     *     summary="Update menu item status",
     *     operationId="backMenuItemsUpdateStatus",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"is_active"},
     *         @OA\Property(property="is_active", type="boolean", example=true)
     *     )),
     *     @OA\Response(response=200, description="Status updated", @OA\JsonContent(@OA\Property(property="message", type="string"), @OA\Property(property="data", ref="#/components/schemas/BackMenuItem"))),
     *     @OA\Response(response=400, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")),
     *     @OA\Response(response=404, description="Not found", @OA\JsonContent(ref="#/components/schemas/NotFoundResponse"))
     * )
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