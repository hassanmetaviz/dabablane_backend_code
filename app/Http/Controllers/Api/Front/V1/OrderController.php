<?php

namespace App\Http\Controllers\Api\Front\V1;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Customers;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Api\BaseController;
use App\Http\Resources\Front\V1\OrderResource;
use App\Models\Blane;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\OrderConfirmation;
use Illuminate\Support\Facades\Log;
use App\Services\CmiService;
use App\Http\Traits\WebhookNotifiable;
use Carbon\Carbon;

/**
 * @OA\Schema(
 *     schema="Order",
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
 *     @OA\Property(property="payment_method", type="string", enum={"cash", "online", "partiel"}, example="cash"),
 *     @OA\Property(property="source", type="string", enum={"web", "mobile", "agent"}, example="web"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="OrderCreateRequest",
 *     type="object",
 *     required={"blane_id", "name", "email", "phone", "quantity", "payment_method", "total_price"},
 *     @OA\Property(property="blane_id", type="integer", example=1, description="ID of the blane to order"),
 *     @OA\Property(property="name", type="string", example="John Doe", description="Customer name"),
 *     @OA\Property(property="email", type="string", format="email", example="john@example.com", description="Customer email"),
 *     @OA\Property(property="phone", type="string", example="+212612345678", description="Customer phone"),
 *     @OA\Property(property="quantity", type="integer", minimum=1, example=1, description="Order quantity"),
 *     @OA\Property(property="delivery_address", type="string", example="123 Main St", description="Delivery address (required for non-digital products)"),
 *     @OA\Property(property="city", type="string", example="Casablanca", description="City (required for non-digital products)"),
 *     @OA\Property(property="payment_method", type="string", enum={"cash", "online", "partiel"}, example="cash"),
 *     @OA\Property(property="total_price", type="number", format="float", example=199.99),
 *     @OA\Property(property="partiel_price", type="number", format="float", example=50.00, description="Partial payment amount (required if payment_method is partiel)"),
 *     @OA\Property(property="comments", type="string", example="Please deliver after 6 PM"),
 *     @OA\Property(property="source", type="string", enum={"web", "mobile", "agent"}, example="web")
 * )
 */
class OrderController extends BaseController
{
    use WebhookNotifiable;

    private $paymentService;
    private $gatewayUrl;

    public function __construct(CmiService $paymentService)
    {
        $this->paymentService = $paymentService;
        $this->gatewayUrl = config('cmi.base_uri');
    }

    /**
     * Check daily availability for orders
     *
     * @param int $blaneId
     * @param int $quantity
     * @return bool
     */
    protected function checkDailyOrderAvailability($blaneId, $quantity): bool
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
    protected function getRemainingDailyOrderAvailability($blaneId): int
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
     * @OA\Post(
     *     path="/front/v1/orders",
     *     tags={"Orders"},
     *     summary="Create a new order",
     *     description="Create a new order for a blane (product/service). Returns order details and payment information if applicable.",
     *     operationId="createOrder",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/OrderCreateRequest")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Order created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="code", type="integer", example=201),
     *             @OA\Property(property="message", type="string", example="Order created successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="order", ref="#/components/schemas/Order"),
     *                 @OA\Property(
     *                     property="cancellation",
     *                     type="object",
     *                     @OA\Property(property="url", type="string"),
     *                     @OA\Property(property="token", type="string"),
     *                     @OA\Property(property="timestamp", type="integer")
     *                 ),
     *                 @OA\Property(
     *                     property="payment_info",
     *                     type="object",
     *                     description="Only returned for online/partiel payment methods",
     *                     @OA\Property(property="payment_url", type="string"),
     *                     @OA\Property(property="method", type="string", example="post"),
     *                     @OA\Property(property="inputs", type="object")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request (stock exceeded, etc.)",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error or daily limit reached",
     *         @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")
     *     )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $blaneId = $request->input('blane_id');
        $blane = Blane::findOrFail($blaneId);

        $rules = [
            'blane_id' => 'required|integer|exists:blanes,id',
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'phone' => 'required|string|max:20',
            'quantity' => 'required|integer|min:1',
            'payment_method' => 'required|string|in:cash,online,partiel',
            'total_price' => 'required|numeric|min:0',
            'partiel_price' => 'nullable|numeric|min:0',
            'comments' => 'nullable|string',
            'source' => 'nullable|string|in:web,mobile,agent',
        ];

        if (!$blane->is_digital) {
            $rules['delivery_address'] = 'required|string|max:255';
            $rules['city'] = 'required|string|max:255';
        } else {
            $rules['delivery_address'] = 'nullable|string|max:255';
            $rules['city'] = 'nullable|string|max:255';
        }

        $validatedData = $request->validate($rules);

        $quantity = $validatedData['quantity'];
        if (!$this->checkDailyOrderAvailability($blaneId, $quantity)) {
            $remaining = $this->getRemainingDailyOrderAvailability($blaneId);
            return $this->error('Daily order limit reached. Only ' . $remaining . ' orders available for today.', [], 422);
        }

        try {
            DB::beginTransaction();

            $quantity = $validatedData['quantity'];
            if (!$this->checkDailyOrderAvailability($blaneId, $quantity)) {
                $remaining = $this->getRemainingDailyOrderAvailability($blaneId);
                return $this->error('Daily order limit reached. Only ' . $remaining . ' orders available for today.', [], 422);
            }

            if ($validatedData['quantity'] > $blane->stock) {
                return $this->error('Order quantity exceeds available stock', [], 400);
            }

            if ($validatedData['quantity'] > $blane->max_orders && $blane->max_orders !== 0) {
                return $this->error('Order quantity exceeds maximum allowed orders', [], 400);
            }

            $validatedData['NUM_ORD'] = $this->generateUniqueOrderCode();

            $orderData['total_price'] = $this->calculateTotalPrice($blane, $validatedData);

            $customer = Customers::create([
                'phone' => $validatedData['phone'],
                'name' => $validatedData['name'],
                'email' => $validatedData['email'],
                'city' => $validatedData['city'] ?? null
            ]);

            $order = Order::create([
                'NUM_ORD' => $validatedData['NUM_ORD'],
                'blane_id' => $validatedData['blane_id'],
                'customers_id' => $customer->id,
                'phone' => $validatedData['phone'],
                'quantity' => $validatedData['quantity'],
                'total_price' => $validatedData['total_price'],
                'partiel_price' => $validatedData['partiel_price'] ?? 0,
                'delivery_address' => $validatedData['delivery_address'] ?? null,
                'payment_method' => $validatedData['payment_method'],
                'comments' => $validatedData['comments'] ?? null,
                'status' => 'pending',
                'source' => $validatedData['source'] ?? null
            ]);

            try {
                if ($validatedData['payment_method'] == 'cash') {
                    Mail::to($validatedData['email'])->cc(config('mail.contact_address'))->send(new OrderConfirmation($order));
                }
            } catch (\Exception $e) {
                Log::error('Failed to send order confirmation email: ' . $e->getMessage());

            }

            $blane->stock -= $validatedData['quantity'];
            $blane->save();

            DB::commit();

            $this->sendWebhookNotification($this->prepareWebhookData($order, 'order'));

            if ($validatedData['payment_method'] == 'cash') {
                return $this->created([
                    'order' => new OrderResource($order),
                    'cancellation' => $this->generateCancellationParams($order),
                ], 'Order created successfully');
            } else {
                $paymentAmount = ($order->payment_method === 'partiel')
                    ? $order->partiel_price
                    : $order->total_price;

                $user = $order->customer;
                $orderData = [
                    'amount' => $paymentAmount,
                    'oid' => $order->NUM_ORD,
                    'email' => $user->email ?? '',
                    'name' => $user->name ?? '',
                    'tel' => $user->phone ?? '',
                    'billToStreet1' => $order->delivery_address ?? '',
                    'billToCity' => $user->city ?? '',
                ];

                $params = $this->paymentService->preparePaymentParams($orderData);

                return $this->created([
                    'order' => new OrderResource($order),
                    'cancellation' => $this->generateCancellationParams($order),
                    'payment_info' => [
                        'payment_url' => $this->gatewayUrl,
                        'method' => 'post',
                        'inputs' => $params
                    ]
                ], 'Order created successfully');
            }
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
    protected function generateUniqueOrderCode(): string
    {
        do {
            $code = 'ORDER-' . substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 2) . str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (Order::where('NUM_ORD', $code)->exists());

        return $code;
    }

    /**
     * Calculate the total price for the order.
     *
     * @param Blane $blane
     * @param array $validatedData
     * @return float
     */
    protected function calculateTotalPrice(Blane $blane, array $validatedData): float
    {
        $basePrice = $blane->price_current * $validatedData['quantity'];

        $deliveryFee = 0;
        if (!$blane->is_digital) {
            $deliveryFee = $blane->city === $validatedData['city'] ? $blane->livraison_in_city : $blane->livraison_out_city;
        }

        return ($basePrice + $deliveryFee) * 0.20;
    }

    /**
     * Display the specified Order.
     *
     * @OA\Get(
     *     path="/front/v1/orders/{id}",
     *     tags={"Orders"},
     *     summary="Get order details",
     *     description="Retrieve details of a specific order by its order number (NUM_ORD)",
     *     operationId="getOrder",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Order number (NUM_ORD)",
     *         @OA\Schema(type="string", example="ORDER-AB123456")
     *     ),
     *     @OA\Parameter(
     *         name="include",
     *         in="query",
     *         description="Include related resources (comma-separated)",
     *         @OA\Schema(type="string", example="blane,customer,shippingDetails")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Order retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", ref="#/components/schemas/Order")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Order not found",
     *         @OA\JsonContent(ref="#/components/schemas/NotFoundResponse")
     *     )
     * )
     */
    public function show($id, Request $request)
    {
        $request->validate([
            'include' => [
                'nullable',
                'string',
                function ($attribute, $value, $fail) {
                    $validIncludes = ['blane', 'blane.blaneImages', 'customer', 'shippingDetails'];
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

        $order = $query->where('NUM_ORD', $id)->first();

        if (!$order) {
            return $this->notFound('Order not found');
        }

        return new OrderResource($order);
    }

    /**
     * Change the status of the specified Order.
     *
     * @OA\Patch(
     *     path="/front/v1/orders/{id}/status",
     *     tags={"Orders"},
     *     summary="Update order status",
     *     description="Change the status of a pending order (used after payment callbacks)",
     *     operationId="changeOrderStatus",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Order number (NUM_ORD)",
     *         @OA\Schema(type="string", example="ORDER-AB123456")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"status"},
     *             @OA\Property(property="status", type="string", enum={"pending", "failed"}, example="pending")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Order status updated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/SuccessResponse")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Order not found",
     *         @OA\JsonContent(ref="#/components/schemas/NotFoundResponse")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")
     *     )
     * )
     */
    public function changeStatus($id, Request $request): JsonResponse
    {
        $request->validate([
            'status' => 'required|string|in:pending,failed',
        ]);

        $order = Order::where('NUM_ORD', $id)->where('status', 'pending')->first();

        if (!$order) {
            return $this->notFound('Order not found');
        }

        $order->status = $request->input('status');
        $order->save();

        return $this->success(new OrderResource($order), 'Order status updated successfully');
    }

    /**
     * Delete the specified Order if status is pending.
     *
     * @OA\Delete(
     *     path="/front/v1/orders/{id}",
     *     tags={"Orders"},
     *     summary="Delete an order",
     *     description="Delete a pending order. Only orders with 'pending' status can be deleted.",
     *     operationId="deleteOrder",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Order ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Order deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="code", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="Order deleted successfully"),
     *             @OA\Property(property="data", type="null")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Cannot delete non-pending order",
     *         @OA\JsonContent(ref="#/components/schemas/ForbiddenResponse")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Order not found",
     *         @OA\JsonContent(ref="#/components/schemas/NotFoundResponse")
     *     )
     * )
     */
    public function destroy($id): JsonResponse
    {
        $order = Order::find($id);

        if (!$order) {
            return $this->notFound('Order not found');
        }

        if ($order->status !== 'pending') {
            return $this->forbidden('Only pending orders can be deleted');
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
     * Generate cancellation params for the order
     * 
     * @param Order $order
     * @return array
     */
    protected function generateCancellationParams(Order $order): array
    {
        $timestamp = now()->timestamp;
        $cancelToken = hash('sha256', $order->cancel_token . '|' . $timestamp);

        return [
            'id' => $order->NUM_ORD,
            'timestamp' => $timestamp,
            'token' => $cancelToken,
        ];
    }

    /**
     * Cancel an order using a secure token.
     *
     * @OA\Post(
     *     path="/front/v1/orders/cancel",
     *     tags={"Orders"},
     *     summary="Cancel order by token",
     *     description="Cancel a pending order using a secure cancellation token. The token is provided when the order is created.",
     *     operationId="cancelOrderByToken",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"id", "token", "timestamp"},
     *             @OA\Property(property="id", type="string", example="ORDER-AB123456", description="Order number"),
     *             @OA\Property(property="token", type="string", example="abc123...", description="Cancellation token"),
     *             @OA\Property(property="timestamp", type="integer", example=1704067200, description="Timestamp from cancellation params")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Order cancelled successfully",
     *         @OA\JsonContent(ref="#/components/schemas/SuccessResponse")
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Order cannot be cancelled (not pending)",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Invalid or expired cancellation token",
     *         @OA\JsonContent(ref="#/components/schemas/ForbiddenResponse")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Order not found",
     *         @OA\JsonContent(ref="#/components/schemas/NotFoundResponse")
     *     )
     * )
     */
    public function cancelByToken(Request $request): JsonResponse
    {
        $request->validate([
            'id' => 'required|string',
            'token' => 'required|string',
            'timestamp' => 'required|numeric',
        ]);

        $order = Order::where('NUM_ORD', $request->id)->first();

        if (!$order) {
            return $this->notFound('Order not found');
        }

        if (!$order->verifyCancellationRequest($request->token, $request->timestamp)) {
            return $this->forbidden('Invalid or expired cancellation token');
        }

        if ($order->status !== 'pending') {
            return $this->error('This order cannot be cancelled anymore', [], 400);
        }

        try {
            DB::beginTransaction();

            $order->status = 'cancelled';
            $order->save();

            $blane = $order->blane;
            if ($blane) {
                $blane->stock += $order->quantity;
                $blane->save();
            }

            DB::commit();

            return $this->success(new OrderResource($order), 'Order cancelled successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to cancel order: ' . $e->getMessage());
            return $this->error('Failed to cancel order', [], 500);
        }
    }
}
