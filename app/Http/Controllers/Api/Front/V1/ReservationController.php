<?php

namespace App\Http\Controllers\Api\Front\V1;

use Illuminate\Http\Request;
use App\Models\Reservation;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;
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


class ReservationController extends Controller
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
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
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
                return response()->json([
                    'error' => 'Daily reservation limit reached. Only ' . $remaining . ' spots available for this date.'
                ], 422);
            }

            if ($reservationType === 'time') {
                if ($this->hasReachedMaxReservations($request->blane_id, $request->date, $request->time)) {
                    return response()->json([
                        'error' => 'This time slot has reached its maximum number of reservations.'
                    ], 422);
                }
            } else {
                if ($this->hasReachedMaxReservations($request->blane_id, $request->date, $request->end_date)) {
                    return response()->json([
                        'error' => 'This date has reached its maximum number of reservations.'
                    ], 422);
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
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        try {
            DB::beginTransaction();

            $blane = Blane::find($validatedData['blane_id']);

            $quantity = $validatedData['quantity'] ?? 1;
            if (!$this->checkDailyAvailability($validatedData['blane_id'], $validatedData['date'], $quantity)) {
                $remaining = $this->getRemainingDailyAvailability($validatedData['blane_id'], $validatedData['date']);
                return response()->json([
                    'error' => 'Daily reservation limit reached. Only ' . $remaining . ' spots available for this date.'
                ], 422);
            }

            if ($blane->nombre_max_reservation < $validatedData['quantity']) {
                return response()->json(['message' => 'Blane is full'], 400);
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
                return response()->json([
                    'message' => 'Reservation created successfully',
                    'data' => new ReservationResource($reservation),
                    'cancellation' => $this->generateCancellationParams($reservation),
                ], 201);
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

                return response()->json([
                    'message' => 'Reservation created successfully',
                    'data' => new ReservationResource($reservation),
                    'cancellation' => $this->generateCancellationParams($reservation),
                    'payment_info' => [
                        'payment_url' => $this->gatewayUrl,
                        'method' => 'post',
                        'inputs' => $params
                    ]
                ], 201);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create Reservation: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to create Reservation',
                'error' => $e->getMessage(),
            ], 500);
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
        try {
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
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        $reservation = Reservation::find($id);

        if (!$reservation) {
            return response()->json(['message' => 'Reservation not found'], 404);
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
                    return response()->json([
                        'message' => 'Daily reservation limit reached. Only ' . $availableSlots . ' spots available for this date.'
                    ], 400);
                }
            }

            if ($blane->nombre_max_reservation <= Reservation::where('blane_id', $validatedData['blane_id'])->where('date', $validatedData['date'])->count()) {
                return response()->json(['message' => 'Blane is full'], 400);
            }
            if ($blane->personnes_prestation < $validatedData['number_persons']) {
                return response()->json(['message' => 'Number of persons is greater than the number of persons in the blane'], 400);
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

            return response()->json([
                'message' => 'Reservation updated successfully',
                'data' => new ReservationResource($reservation),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update Reservation',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Change the status of the specified Reservation.
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
            return response()->json(['message' => 'Reservation not found'], 404);
        }

        $reservation->status = $request->input('status');
        $reservation->save();

        return response()->json([
            'message' => 'Reservation status updated successfully',
            'data' => new ReservationResource($reservation),
        ]);
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
            return response()->json(['message' => 'Reservation not found'], 404);
        }

        try {
            $reservation->delete();
            return response()->json([
                'message' => 'Reservation deleted successfully',
            ], 204);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete Reservation',
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
     * @param Request $request
     * @return JsonResponse
     */
    public function getAvailableTimeSlots(Request $request, $slug): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'date' => 'required|date',
            ]);

            $date = $validatedData['date'];

            $blane = Blane::where('slug', $slug)->first();
            if (!$blane) {
                return response()->json(['message' => 'Blane not found'], 404);
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

                return response()->json([
                    'type' => 'time',
                    'data' => $timeSlots,
                    'daily_availability' => [
                        'remaining' => $dailyAvailability,
                        'limit' => $dailyLimit,
                        'has_daily_limit' => $dailyLimit !== null
                    ]
                ], 200);
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

                return response()->json([
                    'type' => 'date',
                    'data' => [
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
                ], 200);
            }
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        } catch (\Exception $e) {
            Log::error('Failed to get available time slots: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to get available time slots',
                'error' => $e->getMessage(),
            ], 500);
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
     * @param Request $request
     * @return JsonResponse
     */
    public function cancelByToken(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'id' => 'required|string',
                'token' => 'required|string',
                'timestamp' => 'required|numeric',
            ]);

            $reservation = Reservation::where('NUM_RES', $request->id)->first();

            if (!$reservation) {
                return response()->json(['message' => 'Reservation not found'], 404);
            }

            if (!$reservation->verifyCancellationRequest($request->token, $request->timestamp)) {
                return response()->json(['message' => 'Invalid or expired cancellation token'], 403);
            }

            if ($reservation->status !== 'pending') {
                return response()->json(['message' => 'This reservation cannot be cancelled anymore'], 400);
            }

            DB::beginTransaction();

            $reservation->status = 'cancelled';
            $reservation->save();

            $blane = $reservation->blane;
            if ($blane) {
                $blane->nombre_max_reservation += $reservation->quantity;
                $blane->save();
            }

            DB::commit();

            return response()->json([
                'message' => 'Reservation cancelled successfully',
                'data' => new ReservationResource($reservation),
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to cancel reservation: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to cancel reservation',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}


