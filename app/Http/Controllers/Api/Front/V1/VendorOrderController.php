<?php

namespace App\Http\Controllers\Api\Front\V1;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Customers;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\Front\V1\OrderResource;
use App\Models\Blane;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\OrderConfirmation;
use App\Mail\OrderUpdated;
use Illuminate\Support\Facades\Log;
use App\Services\CmiService;
use App\Http\Traits\WebhookNotifiable;
use Carbon\Carbon;

class VendorOrderController extends OrderController
{
    use WebhookNotifiable;

    /**
     * Store a newly created Order (Vendor version with override capability).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
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
                'confirm_exceed' => 'nullable|boolean',
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
            $confirmExceed = $request->input('confirm_exceed', false);

            $dailyAvailability = $this->getRemainingDailyOrderAvailability($blaneId);
            $exceedsDailyLimit = $quantity > $dailyAvailability && $blane->availability_per_day !== null;
            $exceedsStockLimit = $quantity > $blane->stock;
            $exceedsMaxOrders = $quantity > $blane->max_orders && $blane->max_orders !== 0;

            if (($exceedsDailyLimit || $exceedsStockLimit || $exceedsMaxOrders) && !$confirmExceed) {
                return response()->json([
                    'requires_confirmation' => true,
                    'message' => 'Quantity required exceeds availability/stock/max orders. Do you still want to confirm?',
                    'availability' => [
                        'daily_available' => $dailyAvailability,
                        'stock_available' => $blane->stock,
                        'max_orders' => $blane->max_orders,
                        'requested_quantity' => $quantity,
                        'exceeds_daily_limit' => $exceedsDailyLimit,
                        'exceeds_stock_limit' => $exceedsStockLimit,
                        'exceeds_max_orders' => $exceedsMaxOrders,
                    ]
                ], 422);
            }

        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        try {
            DB::beginTransaction();

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

            if ($blane->stock >= $validatedData['quantity']) {
                $blane->stock -= $validatedData['quantity'];
            } else {
                $blane->stock -= $validatedData['quantity'];
            }
            $blane->save();

            DB::commit();

            $this->sendWebhookNotification($this->prepareWebhookData($order, 'order'));

            $hasOverride = $exceedsDailyLimit || $exceedsStockLimit || $exceedsMaxOrders;

            if ($validatedData['payment_method'] == 'cash') {
                return response()->json([
                    'message' => 'Order created successfully',
                    'data' => new OrderResource($order),
                    'cancellation' => $this->generateCancellationParams($order),
                    'vendor_override' => $hasOverride,
                ], 201);
            } else {
                return response()->json([
                    'message' => 'Only cash payments are allowed for vendors',
                    'error' => $e->getMessage(),
                ], status: 404);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create Order',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified Order (Vendor version with override capability).
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $order = Order::find($id);

            if (!$order) {
                return response()->json(['message' => 'Order not found'], 404);
            }

            $blaneId = $request->input('blane_id', $order->blane_id);
            $blane = Blane::findOrFail($blaneId);

            $rules = [
                'blane_id' => 'required|integer|exists:blanes,id',
                'name' => 'required|string|max:255',
                'email' => 'required|email',
                'phone' => 'required|string|max:20',
                'quantity' => 'required|integer|min:1',
                'payment_method' => 'required|string|in:cash,online,partiel',
                'total_price' => 'nullable|numeric|min:0',
                'partiel_price' => 'nullable|numeric|min:0',
                'comments' => 'nullable|string',
                'source' => 'nullable|string|in:web,mobile,agent',
                'confirm_exceed' => 'nullable|boolean',
            ];

            if (!$blane->is_digital) {
                $rules['delivery_address'] = 'required|string|max:255';
                $rules['city'] = 'required|string|max:255';
            } else {
                $rules['delivery_address'] = 'nullable|string|max:255';
                $rules['city'] = 'nullable|string|max:255';
            }

            $validatedData = $request->validate($rules);

            DB::beginTransaction();

            $blane = Blane::findOrFail($validatedData['blane_id']);

            $newQuantity = $validatedData['quantity'];
            $oldQuantity = $order->quantity;
            $confirmExceed = $request->input('confirm_exceed', false);

            $exceedsDailyLimit = false;
            $exceedsStockLimit = false;
            $exceedsMaxOrders = false;

            if ($newQuantity !== $oldQuantity) {
                $orderDate = $order->created_at->toDateString();
                $today = Carbon::today()->toDateString();

                if ($orderDate === $today) {
                    $dailyOrdersExcludingThis = Order::where('blane_id', $blane->id)
                        ->whereDate('created_at', $today)
                        ->where('id', '!=', $order->id)
                        ->where('status', '!=', 'cancelled')
                        ->sum('quantity');

                    $dailyAvailability = $blane->availability_per_day !== null
                        ? max(0, $blane->availability_per_day - $dailyOrdersExcludingThis)
                        : 9999;

                    $exceedsDailyLimit = $newQuantity > $dailyAvailability && $blane->availability_per_day !== null;
                }

                $availableStock = $blane->stock + $oldQuantity;
                $exceedsStockLimit = $newQuantity > $availableStock;

                $exceedsMaxOrders = $newQuantity > $blane->max_orders && $blane->max_orders !== 0;

                if (($exceedsDailyLimit || $exceedsStockLimit || $exceedsMaxOrders) && !$confirmExceed) {
                    return response()->json([
                        'requires_confirmation' => true,
                        'message' => 'Updated quantity exceeds availability/stock/max orders. Do you still want to confirm?',
                        'availability' => [
                            'daily_available' => $dailyAvailability ?? 9999,
                            'stock_available' => $availableStock,
                            'max_orders' => $blane->max_orders,
                            'requested_quantity' => $newQuantity,
                            'current_quantity' => $oldQuantity,
                            'exceeds_daily_limit' => $exceedsDailyLimit,
                            'exceeds_stock_limit' => $exceedsStockLimit,
                            'exceeds_max_orders' => $exceedsMaxOrders,
                        ]
                    ], 422);
                }

                $blane->stock += $oldQuantity;
                $blane->stock -= $newQuantity;
                $blane->save();

                if (!isset($validatedData['total_price']) || $validatedData['total_price'] == 0) {
                    $validatedData['total_price'] = $this->calculateTotalPrice($blane, $validatedData);
                }
            }

            $customer = Customers::where('phone', $validatedData['phone'])->first();
            if ($customer) {
                $customer->update([
                    'name' => $validatedData['name'],
                    'email' => $validatedData['email'],
                    'city' => $validatedData['city'] ?? $customer->city,
                ]);
                $validatedData['customers_id'] = $customer->id;
            } else {

                $customer = Customers::create([
                    'phone' => $validatedData['phone'],
                    'name' => $validatedData['name'],
                    'email' => $validatedData['email'],
                    'city' => $validatedData['city'] ?? null
                ]);
                $validatedData['customers_id'] = $customer->id;
            }

            $order->update($validatedData);

            try {
                Mail::to($validatedData['email'])->cc(config('mail.contact_address'))->send(new OrderUpdated($order));
            } catch (\Exception $e) {
                Log::error('Failed to send order update email: ' . $e->getMessage());
            }

            DB::commit();

            $hasOverride = $exceedsDailyLimit || $exceedsStockLimit || $exceedsMaxOrders;

            return response()->json([
                'message' => 'Order updated successfully',
                'data' => new OrderResource($order),
                'vendor_override' => $hasOverride,
            ]);
        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json(['error' => $e->errors()], 400);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update Order',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}




