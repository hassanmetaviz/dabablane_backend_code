<?php

namespace App\Http\Controllers\Api\Back\V1;

use Illuminate\Http\Request;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Api\BaseController;
use App\Http\Resources\Back\V1\OrderResource;
use App\Models\Blane;
use App\Models\Customers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\OrderConfirmation;
use App\Mail\OrderUpdated;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * @OA\Schema(
 *     schema="BackOrder",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="NUM_ORD", type="string", example="ORDER-AB123456"),
 *     @OA\Property(property="blane_id", type="integer", example=1),
 *     @OA\Property(property="customers_id", type="integer", example=1),
 *     @OA\Property(property="quantity", type="integer", example=2),
 *     @OA\Property(property="total_price", type="number", format="float", example=199.99),
 *     @OA\Property(property="partiel_price", type="number", format="float", example=50.00),
 *     @OA\Property(property="delivery_address", type="string", example="123 Main St"),
 *     @OA\Property(property="status", type="string", enum={"pending", "confirmed", "paid", "shipped", "cancelled"}, example="pending"),
 *     @OA\Property(property="payment_method", type="string", enum={"cash", "online"}, example="cash"),
 *     @OA\Property(property="source", type="string", enum={"web", "mobile", "agent"}, example="web"),
 *     @OA\Property(property="blane", ref="#/components/schemas/Blane"),
 *     @OA\Property(property="customer", ref="#/components/schemas/Customer"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="Customer",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="John Doe"),
 *     @OA\Property(property="email", type="string", format="email", example="john@example.com"),
 *     @OA\Property(property="phone", type="string", example="+212612345678"),
 *     @OA\Property(property="city", type="string", example="Casablanca")
 * )
 */
class OrderController extends BaseController
{
    /**
     * Display a listing of the Orders.
     *
     * @OA\Get(
     *     path="/back/v1/orders",
     *     tags={"Orders"},
     *     summary="Get all orders (Admin)",
     *     description="Retrieve a paginated list of all orders with optional filtering, sorting, and related resources",
     *     operationId="getBackOrders",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="include",
     *         in="query",
     *         description="Include related resources (comma-separated: blane,user,shippingDetails,customer)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="paginationSize",
     *         in="query",
     *         description="Number of items per page",
     *         @OA\Schema(type="integer", minimum=1, default=10)
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Sort field",
     *         @OA\Schema(type="string", enum={"created_at", "total_price", "status", "source"})
     *     ),
     *     @OA\Parameter(
     *         name="sort_order",
     *         in="query",
     *         description="Sort order",
     *         @OA\Schema(type="string", enum={"asc", "desc"})
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by order status",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="blane_id",
     *         in="query",
     *         description="Filter by blane ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="source",
     *         in="query",
     *         description="Filter by order source",
     *         @OA\Schema(type="string", enum={"web", "mobile", "agent"})
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search term",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Orders retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/BackOrder")),
     *             @OA\Property(property="links", ref="#/components/schemas/PaginationLinks"),
     *             @OA\Property(property="meta", ref="#/components/schemas/PaginationMeta")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")
     *     )
     * )
     */
    public function index(Request $request)
    {
        $request->validate([
            'include' => [
                'nullable',
                'string',
                function ($attribute, $value, $fail) {
                    $validIncludes = ['blane', 'user', 'shippingDetails', 'customer'];
                    $includes = explode(',', $value);
                    foreach ($includes as $include) {
                        if (!in_array($include, $validIncludes)) {
                            $fail('The selected ' . $attribute . ' is invalid.');
                        }
                    }
                },
            ],
            'paginationSize' => 'nullable|integer|min:1',
            'sort_by' => 'nullable|string|in:created_at,total_price,status,source',
            'sort_order' => 'nullable|string|in:asc,desc',
            'search' => 'nullable|string',
            'status' => 'nullable|string',
            'blane_id' => 'nullable|integer',
            'source' => 'nullable|string|in:web,mobile,agent',
        ]);

        $query = Order::query();

        $this->applyFilters($request, $query);
        $this->applySearch($request, $query);
        $this->applySorting($request, $query);

        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $query->with($includes);
        }

        $paginationSize = $request->input('paginationSize', 10);
        $orders = $query->paginate($paginationSize);

        return OrderResource::collection($orders);
    }

    /**
     * Display the specified Order.
     *
     * @param int $id
     * @param Request $request
     */
    public function show($id, Request $request)
    {
        $request->validate([
            'include' => [
                'nullable',
                'string',
                function ($attribute, $value, $fail) {
                    $validIncludes = ['blane', 'user', 'shippingDetails'];
                    $includes = explode(',', $value);
                    foreach ($includes as $include) {
                        if (!in_array($include, $validIncludes)) {
                            $fail('The selected ' . $attribute . ' is invalid.');
                        }
                    }
                },
            ],
        ]);

        $query = Order::query();

        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $query->with($includes);
        }

        $order = $query->find($id);

        if (!$order) {
            return $this->notFound('Order not found');
        }

        return new OrderResource($order);
    }

    /**
     * Check daily availability for orders
     *
     * @param int $blaneId
     * @param int $quantity
     * @return bool
     */
    private function checkDailyOrderAvailability($blaneId, $quantity): bool
    {
        $blane = Blane::findOrFail($blaneId);

        if ($blane->availability_per_day === null) {
            return true;
        }

        if ($blane->availability_per_day === 0) {
            return false;
        }

        $today = Carbon::today()->toDateString();
        $dailyOrders = Order::where('blane_id', $blaneId)
            ->whereDate('created_at', $today)
            ->where('status', '!=', 'cancelled')
            ->sum('quantity');

        $availableSlots = $blane->availability_per_day - $dailyOrders;

        return $availableSlots >= $quantity;
    }

    /**
     * Get remaining daily order availability
     *
     * @param int $blaneId
     * @return int
     */
    private function getRemainingDailyOrderAvailability($blaneId): int
    {
        $blane = Blane::findOrFail($blaneId);

        if ($blane->availability_per_day === null) {
            return 9999;
        }

        if ($blane->availability_per_day === 0) {
            return 0;
        }

        $today = Carbon::today()->toDateString();
        $dailyOrders = Order::where('blane_id', $blaneId)
            ->whereDate('created_at', $today)
            ->where('status', '!=', 'cancelled')
            ->sum('quantity');

        return max(0, $blane->availability_per_day - $dailyOrders);
    }

    /**
     * Store a newly created Order.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'blane_id' => 'required|integer|exists:blanes,id',
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'phone' => 'required|string|max:20',
            'city' => 'required|string|max:255',
            'quantity' => 'required|integer|min:1',
            'delivery_address' => 'required|string|max:255',
            'total_price' => 'nullable|numeric|min:0',
            'partiel_price' => 'nullable|numeric|min:0',
            'payment_method' => 'nullable|string|in:cash,online',
            'status' => 'required|string|in:confirmed,paid,pending,shipped,cancelled',
            'source' => 'nullable|string|in:web,mobile,agent',
        ]);

        if (!isset($validatedData['partiel_price']) || $validatedData['partiel_price'] === null) {
            $validatedData['partiel_price'] = 0;
        }

        try {
            DB::beginTransaction();

            // Get the blane and check stock and max_orders
            $blane = Blane::findOrFail($validatedData['blane_id']);

            // Check daily availability
            $quantity = $validatedData['quantity'];
            if (!$this->checkDailyOrderAvailability($validatedData['blane_id'], $quantity)) {
                $remaining = $this->getRemainingDailyOrderAvailability($validatedData['blane_id']);
                return $this->error('Daily order limit reached. Only ' . $remaining . ' orders available for today.', [], 400);
            }

            if ($validatedData['quantity'] > $blane->stock) {
                return $this->error('Order quantity exceeds available stock', [], 400);
            }

            if ($validatedData['quantity'] > $blane->max_orders) {
                return $this->error('Order quantity exceeds maximum allowed orders', [], 400);
            }

            if (!isset($validatedData['total_price']) || $validatedData['total_price'] === null || $validatedData['total_price'] == 0) {
                $validatedData['total_price'] = $blane->price * $validatedData['quantity'];
            }

            $validatedData['NUM_ORD'] = $this->generateUniqueOrderCode();

            $customer = Customers::create([
                'phone' => $validatedData['phone'],
                'name' => $validatedData['name'],
                'email' => $validatedData['email'],
                'city' => $validatedData['city']
            ]);

            // Create order with all necessary data
            $orderData = [
                'NUM_ORD' => $validatedData['NUM_ORD'],
                'blane_id' => $validatedData['blane_id'],
                'customers_id' => $customer->id,
                'phone' => $validatedData['phone'],
                'quantity' => $validatedData['quantity'],
                'total_price' => $validatedData['total_price'],
                'partiel_price' => $validatedData['partiel_price'],
                'delivery_address' => $validatedData['delivery_address'],
                'status' => $validatedData['status'],
                'source' => $validatedData['source'],
            ];

            $order = Order::create($orderData);

            try {
                Mail::to($validatedData['email'])->send(new OrderConfirmation($order));
            } catch (\Exception $e) {
                Log::error('Failed to send order confirmation email: ' . $e->getMessage());
                // Continue execution even if email fails
            }

            // Update blane stock
            $blane->stock -= $validatedData['quantity'];
            $blane->save();

            DB::commit();

            return $this->created(new OrderResource($order), 'Order created successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to create Order', [], 500);
        }
    }

    /**
     * Generate a unique order code.
     *
     * @return string
     */
    private function generateUniqueOrderCode(): string
    {
        do {
            $code = 'ORDER-' . substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 2) . str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (Order::where('NUM_ORD', $code)->exists());

        return $code;
    }

    /**
     * Update the specified Order.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, $id): JsonResponse
    {
        $order = Order::find($id);

        if (!$order) {
            return $this->notFound('Order not found');
        }

        if ($order->status !== 'pending') {
            return $this->forbidden('Only pending orders can be updated');
        }

        $validatedData = $request->validate([
            'blane_id' => 'required|integer|exists:blanes,id',
            'name' => 'required|string',
            'email' => 'required|email',
            'quantity' => 'required|integer|min:1',
            'city' => 'required|string',
            'phone' => 'required|string|max:20',
            'total_price' => 'nullable|numeric|min:0',
            'partiel_price' => 'nullable|numeric|min:0',
            'delivery_address' => 'required|string|max:255',
            'payment_method' => 'nullable|string|in:cash,online,partiel',
            'status' => 'required|string|in:confirmed,pending,paid,shipped,cancelled',
            'source' => 'nullable|string|in:web,mobile,agent',
        ]);

        try {

            DB::beginTransaction();

            $blane = Blane::findOrFail($validatedData['blane_id'] ?? $order->blane_id);

            if (isset($validatedData['quantity'])) {
                $newQuantity = $validatedData['quantity'];
                $oldQuantity = $order->quantity;

                $orderDate = $order->created_at->toDateString();
                $today = Carbon::today()->toDateString();

                if ($orderDate === $today) {
                    $dailyOrdersExcludingThis = Order::where('blane_id', $blane->id)
                        ->whereDate('created_at', $today)
                        ->where('id', '!=', $order->id)
                        ->where('status', '!=', 'cancelled')
                        ->sum('quantity');

                    $availableSlots = $blane->availability_per_day - $dailyOrdersExcludingThis;

                    if ($newQuantity > $availableSlots && $blane->availability_per_day !== null) {
                        return $this->error('Daily order limit reached. Only ' . $availableSlots . ' orders available for today.', [], 400);
                    }
                }

                if ($newQuantity > $blane->stock + $oldQuantity) {
                    return $this->error('Order quantity exceeds available stock', [], 400);
                }

                if ($newQuantity > $blane->max_orders) {
                    return $this->error('Order quantity exceeds maximum allowed orders', [], 400);
                }
                if ($validatedData['total_price'] == null || $validatedData['total_price'] == 0) {
                    $validatedData['total_price'] = $blane->price * $newQuantity;
                }

                $blane->stock += $oldQuantity;
                $blane->stock -= $newQuantity;
                $blane->save();
            }

            $customer = Customers::create([
                'phone' => $validatedData['phone'],
                'name' => $validatedData['name'],
                'email' => $validatedData['email'],
                'city' => $validatedData['city']
            ]);

            $validatedData['customers_id'] = $customer->id;
            $order->update($validatedData);

            try {
                Mail::to($validatedData['email'])->send(new OrderUpdated($order));
            } catch (\Exception $e) {
                Log::error('Failed to send order update email: ' . $e->getMessage());
            }

            DB::commit();

            return $this->success(new OrderResource($order), 'Order updated successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to update Order', [], 500);
        }
    }

    /**
     * Remove the specified Order.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        $order = Order::find($id);

        if (!$order) {
            return $this->notFound('Order not found');
        }

        try {
            $order->delete();
            return $this->deleted('Order deleted successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to delete Order', [], 500);
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

        if ($request->has('email')) {
            $email = $request->input('email');
            $query->whereHas('customer', function ($q) use ($email) {
                $q->where('email', 'like', "%$email%");
            });
        }
        if ($request->has('source')) {
            $query->where('source', $request->input('source'));
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
            $query->where('delivery_address', 'like', "%$search%")
                ->orWhere('status', 'like', "%$search%")
                ->orWhere('source', 'like', "%$search%");
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

        $allowedSortBy = ['created_at', 'total_price', 'status', 'source'];
        $allowedSortOrder = ['asc', 'desc'];

        if (in_array($sortBy, $allowedSortBy) && in_array($sortOrder, $allowedSortOrder)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('created_at', 'desc');
        }
    }
    /**
     * change the status of an order
     *
     * @param Request $request
     */
    public function updateStatus(Request $request, $id)
    {
        $order = Order::find($id);

        if (!$order) {
            return $this->notFound('Order not found');
        }

        $request->validate([
            'status' => 'required|string',
        ]);

        $order->status = $request->input('status');
        $order->save();

        return $this->success(new OrderResource($order), 'Order status updated successfully');
    }

    public function getOrdersList(Request $request)
    {
        $request->validate([
            'include' => [
                'nullable',
                'string',
                function ($attribute, $value, $fail) {
                    $validIncludes = ['blane', 'user', 'shippingDetails', 'customer', 'blaneImage'];
                    $includes = explode(',', $value);
                    foreach ($includes as $include) {
                        if (!in_array($include, $validIncludes)) {
                            $fail('The selected ' . $attribute . ' is invalid.');
                        }
                    }
                },
            ],
            'paginationSize' => 'nullable|integer|min:1',
            'sort_by' => 'nullable|string|in:created_at,total_price,status,source',
            'sort_order' => 'nullable|string|in:asc,desc',
            'search' => 'nullable|string',
            'status' => 'nullable|string',
            'blane_id' => 'nullable|integer',
            'source' => 'nullable|string|in:web,mobile,agent',
        ]);

        $query = Order::query();

        $this->applyFilters($request, $query);
        $this->applySearch($request, $query);
        $this->applySorting($request, $query);

        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $includes = array_map(function ($include) {
                return $include === 'blaneImage' ? 'blane.blaneImages' : $include;
            }, $includes);
            $query->with($includes);
        }

        $paginationSize = $request->input('paginationSize', 10);
        $orders = $query->paginate($paginationSize);

        return OrderResource::collection($orders);
    }
}
