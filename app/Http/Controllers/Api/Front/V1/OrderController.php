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
     * @param Request $request
     * @return JsonResponse
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
     * @param int $id
     * @param Request $request
     * @return JsonResponse
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
     * @param int $id
     * @return JsonResponse
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
     * @param Request $request
     * @return JsonResponse
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
