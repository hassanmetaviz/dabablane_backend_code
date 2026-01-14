<?php

namespace App\Http\Controllers\Api\Back\V1;

use Illuminate\Http\Request;
use App\Models\Customers;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Api\BaseController;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\Back\V1\CustomersResource;

/**
 * @OA\Schema(
 *     schema="BackCustomer",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="John Doe"),
 *     @OA\Property(property="email", type="string", format="email", example="john@example.com"),
 *     @OA\Property(property="phone", type="string", example="+212612345678"),
 *     @OA\Property(property="address", type="string", example="123 Main St"),
 *     @OA\Property(property="city", type="string", example="Casablanca"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class CustomersController extends BaseController
{
    /**
     * Display a listing of the Customers.
     *
     * @OA\Get(
     *     path="/back/v1/customers",
     *     tags={"Back - Customers"},
     *     summary="List all customers",
     *     operationId="backCustomersIndex",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="paginationSize", in="query", @OA\Schema(type="integer", default=10)),
     *     @OA\Parameter(name="sort_by", in="query", @OA\Schema(type="string", enum={"created_at", "name", "email", "phone", "city"})),
     *     @OA\Parameter(name="sort_order", in="query", @OA\Schema(type="string", enum={"asc", "desc"})),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="Customers retrieved",
     *         @OA\JsonContent(@OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/BackCustomer")), @OA\Property(property="links", ref="#/components/schemas/PaginationLinks"), @OA\Property(property="meta", ref="#/components/schemas/PaginationMeta"))
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
                'sort_by' => 'nullable|string|in:created_at,name,email,phone,city',
                'sort_order' => 'nullable|string|in:asc,desc',
                'search' => 'nullable|string',                
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        $query = Customers::query();

        $this->applyFilters($request, $query);
        $this->applySearch($request, $query);
        $this->applySorting($request, $query);

        $paginationSize = $request->input('paginationSize', 10);
        $customers = $query->paginate($paginationSize);

        return CustomersResource::collection($customers);
    }

    /**
     * Display the specified Customers.
     *
     * @OA\Get(
     *     path="/back/v1/customers/{id}",
     *     tags={"Back - Customers"},
     *     summary="Get a specific customer",
     *     operationId="backCustomersShow",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Customer retrieved", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/BackCustomer"))),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")),
     *     @OA\Response(response=404, description="Not found", @OA\JsonContent(ref="#/components/schemas/NotFoundResponse"))
     * )
     *
     * @param int $id
     * @param Request $request
     */
    public function show($id, Request $request)
    {
        $customers = Customers::find($id);

        if (!$customers) {
            return response()->json(['message' => 'Customer not found'], 404);
        }

        return new CustomersResource($customers);
    }

    /**
     * Store a newly created customers.
     *
     * @OA\Post(
     *     path="/back/v1/customers",
     *     tags={"Back - Customers"},
     *     summary="Create a new customer",
     *     operationId="backCustomersStore",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"name", "email", "phone", "address", "city"},
     *         @OA\Property(property="name", type="string", maxLength=255, example="John Doe"),
     *         @OA\Property(property="email", type="string", format="email", maxLength=255, example="john@example.com"),
     *         @OA\Property(property="phone", type="string", maxLength=255, example="+212612345678"),
     *         @OA\Property(property="address", type="string", maxLength=255, example="123 Main St"),
     *         @OA\Property(property="city", type="string", maxLength=255, example="Casablanca")
     *     )),
     *     @OA\Response(response=201, description="Customer created", @OA\JsonContent(@OA\Property(property="message", type="string"), @OA\Property(property="data", ref="#/components/schemas/BackCustomer"))),
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
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'phone' => 'required|string|max:255',
                'address' => 'required|string|max:255',
                'city' => 'required|string|max:255',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        try {
            $customers = Customers::create($validatedData);
            return response()->json([
                'message' => 'Customers created successfully',
                'data' => new CustomersResource($customers),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create Customers',
            ], 500);
        }
    }

    /**
     * Update the specified customers.
     *
     * @OA\Put(
     *     path="/back/v1/customers/{id}",
     *     tags={"Back - Customers"},
     *     summary="Update a customer",
     *     operationId="backCustomersUpdate",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"name", "email", "phone", "address", "city"},
     *         @OA\Property(property="name", type="string", maxLength=255),
     *         @OA\Property(property="email", type="string", format="email", maxLength=255),
     *         @OA\Property(property="phone", type="string", maxLength=255),
     *         @OA\Property(property="address", type="string", maxLength=255),
     *         @OA\Property(property="city", type="string", maxLength=255)
     *     )),
     *     @OA\Response(response=200, description="Customer updated", @OA\JsonContent(@OA\Property(property="message", type="string"), @OA\Property(property="data", ref="#/components/schemas/BackCustomer"))),
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
               'name' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'phone' => 'required|string|max:255',
                'address' => 'required|string|max:255',
                'city' => 'required|string|max:255',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        $customers = Customers::find($id);

        if (!$customers) {
            return response()->json(['message' => 'Customers not found'], 404);
        }

        try {
            $customers->update($validatedData);
            return response()->json([
                'message' => 'Customers updated successfully',
                'data' => new CustomersResource($customers),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update customer',
            ], 500);
        }
    }

    /**
     * Remove the specified customers.
     *
     * @OA\Delete(
     *     path="/back/v1/customers/{id}",
     *     tags={"Back - Customers"},
     *     summary="Delete a customer",
     *     operationId="backCustomersDestroy",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Customer deleted"),
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
        $customers = Customers::find($id);

        if (!$customers) {
            return response()->json(['message' => 'Customers not found'], 404);
        }

        try {
            $customers->delete();
            return response()->json([
                'message' => 'Customers deleted successfully',
            ], 204);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete Customers',
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
            $query->where('email', 'like', "%$search%")
                ->orWhere('name', 'like', "%$search%");
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

        $allowedSortBy = ['created_at', 'name', 'email', 'phone', 'city'];
        $allowedSortOrder = ['asc', 'desc'];

        if (in_array($sortBy, $allowedSortBy) && in_array($sortOrder, $allowedSortOrder)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('created_at', 'desc');
        }
    }
}