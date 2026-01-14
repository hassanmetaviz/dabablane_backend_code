<?php

namespace App\Http\Controllers\Api\Front\V1;

use Illuminate\Http\Request;
use App\Models\Reservation;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Api\BaseController;
use App\Http\Resources\Front\V1\ReservationResource;
use App\Models\Customers;
use Illuminate\Support\Facades\Log;
use App\Models\Blane;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Mail;
use App\Mail\ReservationConfirmation;
use App\Mail\ReservationUpdated;
use Illuminate\Support\Facades\DB;
use App\Services\CmiService;
use App\Http\Traits\WebhookNotifiable;
use Carbon\Carbon;

/**
 * @OA\Schema(
 *     schema="Reservation",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="NUM_RES", type="string", example="RES-AB123456"),
 *     @OA\Property(property="blane_id", type="integer", example=1),
 *     @OA\Property(property="customers_id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="John Doe"),
 *     @OA\Property(property="email", type="string", format="email", example="john@example.com"),
 *     @OA\Property(property="phone", type="string", example="+212612345678"),
 *     @OA\Property(property="date", type="string", format="date", example="2024-12-25"),
 *     @OA\Property(property="time", type="string", format="time", example="14:00"),
 *     @OA\Property(property="end_date", type="string", format="date", example="2024-12-26"),
 *     @OA\Property(property="number_persons", type="integer", example=4),
 *     @OA\Property(property="quantity", type="integer", example=1),
 *     @OA\Property(property="total_price", type="number", format="float", example=500.00),
 *     @OA\Property(property="partiel_price", type="number", format="float", example=100.00),
 *     @OA\Property(property="payment_method", type="string", enum={"cash", "online", "partiel"}, example="cash"),
 *     @OA\Property(property="status", type="string", enum={"pending", "confirmed", "cancelled", "failed"}, example="pending"),
 *     @OA\Property(property="comments", type="string", example="Special requests"),
 *     @OA\Property(property="source", type="string", enum={"web", "mobile", "agent"}, example="web"),
 *     @OA\Property(property="city", type="string", example="Casablanca"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="ReservationCreateRequest",
 *     type="object",
 *     required={"blane_id", "name", "email", "date", "phone", "number_persons", "total_price", "payment_method"},
 *     @OA\Property(property="blane_id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="John Doe"),
 *     @OA\Property(property="email", type="string", format="email", example="john@example.com"),
 *     @OA\Property(property="phone", type="string", example="+212612345678"),
 *     @OA\Property(property="date", type="string", format="date", example="2024-12-25"),
 *     @OA\Property(property="time", type="string", format="time", example="14:00", description="Required if blane type is 'time'"),
 *     @OA\Property(property="end_date", type="string", format="date", example="2024-12-26"),
 *     @OA\Property(property="number_persons", type="integer", example=4),
 *     @OA\Property(property="quantity", type="integer", example=1),
 *     @OA\Property(property="total_price", type="number", format="float", example=500.00),
 *     @OA\Property(property="partiel_price", type="number", format="float", example=100.00, description="Required if payment_method is 'partiel'"),
 *     @OA\Property(property="payment_method", type="string", enum={"cash", "online", "partiel"}, example="cash"),
 *     @OA\Property(property="comments", type="string", example="Special requests"),
 *     @OA\Property(property="city", type="string", example="Casablanca"),
 *     @OA\Property(property="source", type="string", enum={"web", "mobile", "agent"}, example="web")
 * )
 *
 * @OA\Schema(
 *     schema="TimeSlotAvailability",
 *     type="object",
 *     @OA\Property(property="type", type="string", enum={"time", "date"}, example="time"),
 *     @OA\Property(
 *         property="slots",
 *         type="array",
 *         @OA\Items(
 *             type="object",
 *             @OA\Property(property="time", type="string", example="14:00"),
 *             @OA\Property(property="available", type="boolean", example=true),
 *             @OA\Property(property="currentReservations", type="integer", example=2),
 *             @OA\Property(property="maxReservations", type="integer", example=3),
 *             @OA\Property(property="remainingCapacity", type="integer", example=1)
 *         )
 *     ),
 *     @OA\Property(
 *         property="daily_availability",
 *         type="object",
 *         @OA\Property(property="remaining", type="integer", example=10),
 *         @OA\Property(property="limit", type="integer", example=20),
 *         @OA\Property(property="has_daily_limit", type="boolean", example=true)
 *     )
 * )
 */
class ReservationController extends BaseController
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
     * Check daily availability for a blane
     *
     * @param int $blaneId
     * @param string $date
     * @param int $quantity
     * @return bool
     */
    protected function checkDailyAvailability($blaneId, $date, $quantity): bool
    {
        $blane = Blane::findOrFail($blaneId);

        if ($blane->availability_per_day === null) {
            return true;
        }

        // If availability_per_day is 0, no reservations allowed
        if ($blane->availability_per_day === 0) {
            return false;
        }

        $dailyReservations = Reservation::where('blane_id', $blaneId)
            ->whereDate('date', $date)
            ->where('status', '!=', 'cancelled')
            ->sum('quantity');

        $availableSlots = $blane->availability_per_day - $dailyReservations;

        return $availableSlots >= $quantity;
    }

    /**
     * Get remaining daily availability
     *
     * @param int $blaneId
     * @param string $date
     * @return int
     */
    protected function getRemainingDailyAvailability($blaneId, $date): int
    {
        $blane = Blane::findOrFail($blaneId);

        if ($blane->availability_per_day === null) {
            return 9999;
        }

        if ($blane->availability_per_day === 0) {
            return 0;
        }

        $dailyReservations = Reservation::where('blane_id', $blaneId)
            ->whereDate('date', $date)
            ->where('status', '!=', 'cancelled')
            ->sum('quantity');

        return max(0, $blane->availability_per_day - $dailyReservations);
    }

    /**
     * Check if a time slot has reached its maximum reservations
     *
     * @param int $blaneId
     * @param string $date
     * @param string $time
     * @return bool
     */
    private function hasReachedMaxReservations($blaneId, $date, $end_date = null, $time = null): bool
    {
        $blane = Blane::findOrFail($blaneId);
        $maxReservations = $blane->max_reservation_par_creneau ?? 3;
        $reservationType = $blane->type_time ?? 'time';

        if ($reservationType === 'time') {
            if (!$time) {
                return false;
            }

            $currentReservations = Reservation::where('blane_id', $blaneId)
                ->where(function ($query) use ($date, $time) {
                    $query->where('date', $date)
                        ->where('time', $time);
                })
                ->where('status', '!=', 'cancelled')
                ->sum('quantity');

            return $currentReservations >= $maxReservations;
        } else {
            $currentReservations = Reservation::where('blane_id', $blaneId)
                ->where(function ($query) use ($date, $end_date) {
                    $query->where('date', $date)
                        ->where('end_date', $end_date);
                })
                ->where('status', '!=', 'cancelled')
                ->sum('quantity');

            return $currentReservations >= $maxReservations;
        }
    }

    /**
     * Store a newly created Reservation in storage.
     *
     * @OA\Post(
     *     path="/front/v1/reservations",
     *     tags={"Reservations"},
     *     summary="Create a new reservation",
     *     description="Create a new reservation for a blane with payment options",
     *     operationId="createReservation",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/ReservationCreateRequest")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Reservation created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="code", type="integer", example=201),
     *             @OA\Property(property="message", type="string", example="Reservation created successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="reservation", ref="#/components/schemas/Reservation"),
     *                 @OA\Property(
     *                     property="cancellation",
     *                     type="object",
     *                     @OA\Property(property="id", type="string", example="RES-AB123456"),
     *                     @OA\Property(property="timestamp", type="integer", example=1703520000),
     *                     @OA\Property(property="token", type="string", example="abc123...")
     *                 ),
     *                 @OA\Property(
     *                     property="payment_info",
     *                     type="object",
     *                     description="Only present for online/partiel payments",
     *                     @OA\Property(property="payment_url", type="string", example="https://payment.cmi.co.ma"),
     *                     @OA\Property(property="method", type="string", example="post"),
     *                     @OA\Property(property="inputs", type="object")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error or daily limit reached",
     *         @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Blane is full",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $blaneId = $request->input('blane_id');
        $blane = Blane::findOrFail($blaneId);
        $reservationType = $blane->type_time ?? 'time';

        $validationRules = [
            'blane_id' => 'required|exists:blanes,id',
            'date' => 'required|date',
            'number_persons' => 'required|integer|min:1',
            'phone' => 'required|string',
            'comments' => 'nullable|string',
            'payment_method' => 'required|string|in:cash,online,partiel',
            'total_price' => 'required|numeric',
            'partiel_price' => 'nullable|numeric',
            'source' => 'nullable|string|in:web,mobile,agent',
        ];

        if ($reservationType === 'time') {
            $validationRules['time'] = 'required|date_format:H:i';
        }

        if ($request->input('payment_method') === 'partiel') {
            $validationRules['partiel_price'] = 'required|numeric|lt:total_price';
        }

        $request->validate($validationRules);

        $quantity = $request->input('quantity', 1);
        if (!$this->checkDailyAvailability($request->blane_id, $request->date, $quantity)) {
            $remaining = $this->getRemainingDailyAvailability($request->blane_id, $request->date);
            return $this->error('Daily reservation limit reached. Only ' . $remaining . ' spots available for this date.', [], 422);
        }

        if ($reservationType === 'time') {
            if ($this->hasReachedMaxReservations($request->blane_id, $request->date, $request->time)) {
                return $this->error('This time slot has reached its maximum number of reservations.', [], 422);
            }
        } else {
            if ($this->hasReachedMaxReservations($request->blane_id, $request->date, $request->end_date)) {
                return $this->error('This date has reached its maximum number of reservations.', [], 422);
            }
        }

        $validatedData = $request->validate([
            'blane_id' => 'required|integer|exists:blanes,id',
            'name' => 'required|string',
            'email' => 'required|email',
            'date' => 'required|date|after_or_equal:today',
            'phone' => 'required|string|max:20',
            'number_persons' => 'required|integer',
            'total_price' => 'required|numeric|min:0',
            'partiel_price' => 'nullable|numeric|min:0',
            'time' => 'nullable|date_format:H:i',
            'end_date' => 'nullable|date|after_or_equal:date',
            'quantity' => 'nullable|integer',
            'payment_method' => 'required|string|in:cash,online,partiel',
            'comments' => 'nullable|string',
            'city' => 'nullable|string|max:255',
            'source' => 'nullable|string|in:web,mobile,agent',
        ]);

        $validatedData['status'] = 'pending';

        if ($validatedData['date']) {
            $date = \Carbon\Carbon::parse($validatedData['date'])->setTimezone(config('app.timezone'));
            $validatedData['date'] = $date->format('Y-m-d H:i:s');
        }

        if (isset($validatedData['end_date'])) {
            $endDate = \Carbon\Carbon::parse($validatedData['end_date'])->setTimezone(config('app.timezone'));
            $validatedData['end_date'] = $endDate->format('Y-m-d H:i:s');
        }

        try {
            DB::beginTransaction();

            $blane = Blane::find($validatedData['blane_id']);

            $quantity = $validatedData['quantity'] ?? 1;
            if (!$this->checkDailyAvailability($validatedData['blane_id'], $validatedData['date'], $quantity)) {
                $remaining = $this->getRemainingDailyAvailability($validatedData['blane_id'], $validatedData['date']);
                return $this->error('Daily reservation limit reached. Only ' . $remaining . ' spots available for this date.', [], 422);
            }

            if ($blane->nombre_max_reservation < $validatedData['quantity']) {
                return $this->error('Blane is full', [], 400);
            }
            $blane->nombre_max_reservation -= $validatedData['quantity'];
            $blane->save();

            $customer = Customers::create([
                'phone' => $validatedData['phone'],
                'name' => $validatedData['name'],
                'email' => $validatedData['email'],
                'city' => $validatedData['city'] ?? null
            ]);
            $validatedData['customers_id'] = $customer->id;

            $validatedData['NUM_RES'] = $this->generateUniqueReservationCode();

            $reservation = Reservation::create($validatedData);

            try {
                if ($validatedData['payment_method'] == 'cash') {
                    Mail::to($validatedData['email'])->cc(config('mail.contact_address'))->send(new ReservationConfirmation($reservation));
                }
            } catch (\Exception $e) {
                Log::error('Failed to send reservation confirmation email: ' . $e->getMessage());
            }

            $this->sendWebhookNotification($this->prepareWebhookData($reservation, 'reservation'));

            DB::commit();

            if ($validatedData['payment_method'] == 'cash') {
                return $this->created([
                    'reservation' => new ReservationResource($reservation),
                    'cancellation' => $this->generateCancellationParams($reservation),
                ], 'Reservation created successfully');
            } else {
                $paymentAmount = ($reservation->payment_method === 'partiel')
                    ? $reservation->partiel_price
                    : $reservation->total_price;

                $user = $reservation->customer;
                $orderData = [
                    'amount' => $paymentAmount,
                    'oid' => $reservation->NUM_RES,
                    'email' => $user->email ?? '',
                    'name' => $user->name ?? '',
                    'tel' => $user->phone ?? '',
                    'billToCity' => $user->city ?? '',
                ];

                $params = $this->paymentService->preparePaymentParams($orderData);

                return $this->created([
                    'reservation' => new ReservationResource($reservation),
                    'cancellation' => $this->generateCancellationParams($reservation),
                    'payment_info' => [
                        'payment_url' => $this->gatewayUrl,
                        'method' => 'post',
                        'inputs' => $params
                    ]
                ], 'Reservation created successfully');
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create Reservation: ' . $e->getMessage());
            return $this->error('Failed to create Reservation', [], 500);
        }
    }

    /**
     * Generate a unique reservation code.
     *
     * @return string
     */
    protected function generateUniqueReservationCode(): string
    {
        do {
            $code = 'RES-' . substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 2) . str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (Reservation::where('NUM_RES', $code)->exists());

        return $code;
    }

    /**
     * Update the specified Reservation.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, $id): JsonResponse
    {
        $validatedData = $request->validate([
            'blane_id' => 'required|integer|exists:blanes,id',
            'name' => 'required|string',
            'email' => 'required|email',
            'date' => 'required|date|after_or_equal:today',
            'phone' => 'required|string|max:20',
            'number_persons' => 'required|integer',
            'time' => 'nullable|date_format:H:i',
            'end_date' => 'nullable|date|after_or_equal:date',
            'total_price' => 'required|numeric|min:0',
            'partiel_price' => 'nullable|numeric|min:0',
            'quantity' => 'nullable|integer',
            'payment_method' => 'required|string|in:cash,online,partiel',
            'comments' => 'nullable|string',
            'source' => 'nullable|string|in:web,mobile,agent',
        ]);

        $reservation = Reservation::find($id);

        if (!$reservation) {
            return $this->notFound('Reservation not found');
        }

        try {
            DB::beginTransaction();

            $blane = Blane::find($validatedData['blane_id']);

            $newDate = $validatedData['date'];
            $oldDate = $reservation->date;
            $newQuantity = $validatedData['quantity'] ?? $reservation->quantity;
            $oldQuantity = $reservation->quantity;

            if ($newDate !== $oldDate || $newQuantity !== $oldQuantity) {
                $dailyReservationsExcludingThis = Reservation::where('blane_id', $blane->id)
                    ->whereDate('date', $newDate)
                    ->where('id', '!=', $reservation->id)
                    ->where('status', '!=', 'cancelled')
                    ->sum('quantity');

                $availableSlots = $blane->availability_per_day - $dailyReservationsExcludingThis;

                if ($newQuantity > $availableSlots && $blane->availability_per_day !== null) {
                    return $this->error('Daily reservation limit reached. Only ' . $availableSlots . ' spots available for this date.', [], 400);
                }
            }

            if ($blane->nombre_max_reservation <= Reservation::where('blane_id', $validatedData['blane_id'])->where('date', $validatedData['date'])->count()) {
                return $this->error('Blane is full', [], 400);
            }
            if ($blane->personnes_prestation < $validatedData['number_persons']) {
                return $this->error('Number of persons is greater than the number of persons in the blane', [], 400);
            }

            $customer = Customers::where('phone', $validatedData['phone'])->first();
            if ($customer) {
                $customer->update([
                    'name' => $validatedData['name'],
                    'email' => $validatedData['email'],
                ]);
            }
            $validatedData['customers_id'] = $customer->id;
            $reservation->update($validatedData);

            try {
                if ($validatedData['payment_method'] == 'cash') {
                    Mail::to($validatedData['email'])
                        ->cc(config('mail.contact_address'))
                        ->send(new ReservationUpdated($reservation));
                }
            } catch (\Exception $e) {
                Log::error('Failed to send reservation update email: ' . $e->getMessage());
            }

            DB::commit();

            return $this->success(new ReservationResource($reservation), 'Reservation updated successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to update Reservation', [], 500);
        }
    }

    /**
     * Change the status of the specified Reservation.
     *
     * @OA\Patch(
     *     path="/front/v1/reservations/{num_res}/status",
     *     tags={"Reservations"},
     *     summary="Change reservation status",
     *     description="Update the status of a pending reservation",
     *     operationId="changeReservationStatus",
     *     @OA\Parameter(
     *         name="num_res",
     *         in="path",
     *         required=true,
     *         description="Reservation number (NUM_RES)",
     *         @OA\Schema(type="string", example="RES-AB123456")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"status"},
     *             @OA\Property(property="status", type="string", enum={"pending", "failed"}, example="failed")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Reservation status updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="code", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="Reservation status updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Reservation")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Reservation not found",
     *         @OA\JsonContent(ref="#/components/schemas/NotFoundResponse")
     *     )
     * )
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

        $reservation = Reservation::where('NUM_RES', $id)->where('status', 'pending')->first();

        if (!$reservation) {
            return $this->notFound('Reservation not found');
        }

        $reservation->status = $request->input('status');
        $reservation->save();

        return $this->success(new ReservationResource($reservation), 'Reservation status updated successfully');
    }

    /**
     * Remove the specified Reservation.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        $reservation = Reservation::find($id);

        if (!$reservation) {
            return $this->notFound('Reservation not found');
        }

        try {
            $reservation->delete();
            return $this->deleted('Reservation deleted successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to delete Reservation', [], 500);
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

        if ($request->has('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->has('date')) {
            $query->where('date', $request->input('date'));
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
            $query->where('comments', 'like', "%$search%")
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

        $allowedSortBy = ['created_at', 'date', 'time', 'status', 'source'];
        $allowedSortOrder = ['asc', 'desc'];

        if (in_array($sortBy, $allowedSortBy) && in_array($sortOrder, $allowedSortOrder)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('created_at', 'desc');
        }
    }

    /**
     * Get available time slots for a specific date and blane
     *
     * @OA\Get(
     *     path="/front/v1/reservations/availability/{slug}",
     *     tags={"Reservations"},
     *     summary="Get available time slots",
     *     description="Get available time slots or date availability for a specific blane and date",
     *     operationId="getAvailableTimeSlots",
     *     @OA\Parameter(
     *         name="slug",
     *         in="path",
     *         required=true,
     *         description="Blane slug",
     *         @OA\Schema(type="string", example="spa-massage")
     *     ),
     *     @OA\Parameter(
     *         name="date",
     *         in="query",
     *         required=true,
     *         description="Date to check availability",
     *         @OA\Schema(type="string", format="date", example="2024-12-25")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Time slots availability retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="code", type="integer", example=200),
     *             @OA\Property(property="data", ref="#/components/schemas/TimeSlotAvailability")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Blane not found",
     *         @OA\JsonContent(ref="#/components/schemas/NotFoundResponse")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")
     *     )
     * )
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getAvailableTimeSlots(Request $request, $slug): JsonResponse
    {
        $validatedData = $request->validate([
            'date' => 'required|date',
        ]);

        $date = $validatedData['date'];

        $blane = Blane::where('slug', $slug)->first();
        if (!$blane) {
            return $this->notFound('Blane not found');
        }

            $reservationType = $blane->type_time ?? 'time';
            $maxReservationsPerSlot = $blane->max_reservation_par_creneau ?? 3;

            $dailyAvailability = $this->getRemainingDailyAvailability($blane->id, $date);
            $dailyLimit = $blane->availability_per_day;

            if ($reservationType === 'time') {
                $availableTimeSlots = $blane->available_time_slots;

                $currentReservations = Reservation::where('blane_id', $blane->id)
                    ->where('date', $date)
                    ->whereNotNull('time')
                    ->whereNull('end_date')
                    ->where('status', '!=', 'cancelled')
                    ->select('time', DB::raw('SUM(quantity) as total_quantity'))
                    ->groupBy('time')
                    ->get()
                    ->keyBy('time')
                    ->toArray();

                $timeSlots = [];
                foreach ($availableTimeSlots as $time) {
                    $current = isset($currentReservations[$time])
                        ? $currentReservations[$time]['total_quantity']
                        : 0;

                    $timeSlots[] = [
                        'time' => $time,
                        'available' => $current < $maxReservationsPerSlot && $dailyAvailability > 0,
                        'currentReservations' => $current,
                        'maxReservations' => $maxReservationsPerSlot,
                        'remainingCapacity' => max(0, $maxReservationsPerSlot - $current),
                        'dailyAvailability' => $dailyAvailability,
                        'dailyLimit' => $dailyLimit
                    ];
                }

                return $this->success([
                    'type' => 'time',
                    'slots' => $timeSlots,
                    'daily_availability' => [
                        'remaining' => $dailyAvailability,
                        'limit' => $dailyLimit,
                        'has_daily_limit' => $dailyLimit !== null
                    ]
                ]);
            } else {
                $currentReservations = Reservation::where('blane_id', $blane->id)
                    ->where('date', $date)
                    ->where('status', '!=', 'cancelled')
                    ->sum('quantity');

                $slotAvailable = $currentReservations < $maxReservationsPerSlot;
                $dailyAvailable = $dailyAvailability > 0;
                $available = $slotAvailable && $dailyAvailable;

                $remainingCapacity = max(0, $maxReservationsPerSlot - $currentReservations);
                $percentageFull = $maxReservationsPerSlot > 0
                    ? round(($currentReservations / $maxReservationsPerSlot) * 100)
                    : 0;

                return $this->success([
                    'type' => 'date',
                    'availability' => [
                        'date' => $date,
                        'available' => $available,
                        'currentReservations' => $currentReservations,
                        'maxReservations' => $maxReservationsPerSlot,
                        'remainingCapacity' => $remainingCapacity,
                        'percentageFull' => $percentageFull,
                        'dailyAvailability' => $dailyAvailability,
                        'dailyLimit' => $dailyLimit
                    ],
                    'daily_availability' => [
                        'remaining' => $dailyAvailability,
                        'limit' => $dailyLimit,
                        'has_daily_limit' => $dailyLimit !== null
                    ]
                ]);
            }
    }

    /**
     * Generate cancellation params for the reservation
     * 
     * @param Reservation $reservation
     * @return array
     */
    protected function generateCancellationParams(Reservation $reservation): array
    {
        $timestamp = now()->timestamp;
        $cancelToken = hash('sha256', $reservation->cancel_token . '|' . $timestamp);

        return [
            'id' => $reservation->NUM_RES,
            'timestamp' => $timestamp,
            'token' => $cancelToken,
        ];
    }

    /**
     * Cancel a reservation using a secure token.
     *
     * @OA\Post(
     *     path="/front/v1/reservations/cancel",
     *     tags={"Reservations"},
     *     summary="Cancel reservation by token",
     *     description="Cancel a pending reservation using a secure cancellation token",
     *     operationId="cancelReservationByToken",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"id", "token", "timestamp"},
     *             @OA\Property(property="id", type="string", example="RES-AB123456", description="Reservation number"),
     *             @OA\Property(property="token", type="string", example="abc123...", description="Cancellation token"),
     *             @OA\Property(property="timestamp", type="integer", example=1703520000, description="Timestamp from cancellation params")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Reservation cancelled successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="code", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="Reservation cancelled successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Reservation")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Invalid or expired cancellation token",
     *         @OA\JsonContent(ref="#/components/schemas/ForbiddenResponse")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Reservation not found",
     *         @OA\JsonContent(ref="#/components/schemas/NotFoundResponse")
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Reservation cannot be cancelled",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
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

        $reservation = Reservation::where('NUM_RES', $request->id)->first();

        if (!$reservation) {
            return $this->notFound('Reservation not found');
        }

        if (!$reservation->verifyCancellationRequest($request->token, $request->timestamp)) {
            return $this->forbidden('Invalid or expired cancellation token');
        }

        if ($reservation->status !== 'pending') {
            return $this->error('This reservation cannot be cancelled anymore', [], 400);
        }

        try {
            DB::beginTransaction();

            $reservation->status = 'cancelled';
            $reservation->save();

            $blane = $reservation->blane;
            if ($blane) {
                $blane->nombre_max_reservation += $reservation->quantity;
                $blane->save();
            }

            DB::commit();

            return $this->success(new ReservationResource($reservation), 'Reservation cancelled successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to cancel reservation: ' . $e->getMessage());
            return $this->error('Failed to cancel reservation', [], 500);
        }
    }
}


