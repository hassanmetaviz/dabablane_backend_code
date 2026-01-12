<?php

namespace App\Http\Controllers\Api\Front\V1;

use Illuminate\Http\Request;
use App\Models\Reservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\Front\V1\ReservationResource;
use App\Models\Customers;
use Illuminate\Support\Facades\Log;
use App\Models\Blane;
use Illuminate\Support\Facades\Mail;
use App\Mail\ReservationConfirmation;
use App\Mail\ReservationUpdated;
use Illuminate\Support\Facades\DB;
use App\Services\CmiService;
use App\Http\Traits\WebhookNotifiable;
use Carbon\Carbon;

class VendorReservationController extends ReservationController
{
    use WebhookNotifiable;

    /**
     * Store a newly created Reservation in storage (Vendor version with override capability).
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
                'confirm_exceed' => 'nullable|boolean',
            ];

            if ($reservationType === 'time') {
                $validationRules['time'] = 'required|date_format:H:i';
            }

            if ($request->input('payment_method') === 'partiel') {
                $validationRules['partiel_price'] = 'required|numeric|lt:total_price';
            }

            $request->validate($validationRules);

            $quantity = $request->input('quantity', 1);
            $confirmExceed = $request->input('confirm_exceed', false);

            $dailyAvailability = $this->getRemainingDailyAvailability($request->blane_id, $request->date);
            $exceedsDailyLimit = $quantity > $dailyAvailability && $blane->availability_per_day !== null;

            $exceedsSlotLimit = false;
            $slotAvailability = null;
            if ($reservationType === 'time') {
                $currentSlotReservations = Reservation::where('blane_id', $request->blane_id)
                    ->where('date', $request->date)
                    ->where('time', $request->time)
                    ->where('status', '!=', 'cancelled')
                    ->sum('quantity');

                $maxReservationsPerSlot = $blane->max_reservation_par_creneau ?? 3;
                $slotAvailability = max(0, $maxReservationsPerSlot - $currentSlotReservations);
                $exceedsSlotLimit = $quantity > $slotAvailability;
            } else {
                $currentReservations = Reservation::where('blane_id', $request->blane_id)
                    ->where('date', $request->date)
                    ->where('status', '!=', 'cancelled')
                    ->sum('quantity');

                $maxReservationsPerSlot = $blane->max_reservation_par_creneau ?? 3;
                $slotAvailability = max(0, $maxReservationsPerSlot - $currentReservations);
                $exceedsSlotLimit = $quantity > $slotAvailability;
            }

            if (($exceedsDailyLimit || $exceedsSlotLimit) && !$confirmExceed) {
                return response()->json([
                    'requires_confirmation' => true,
                    'message' => 'Quantity required exceeds availability for this slot/date. Do you still want to confirm?',
                    'availability' => [
                        'slot_available' => $slotAvailability,
                        'daily_available' => $dailyAvailability,
                        'requested_quantity' => $quantity,
                        'exceeds_slot_limit' => $exceedsSlotLimit,
                        'exceeds_daily_limit' => $exceedsDailyLimit,
                    ]
                ], 422);
            }

            $blane = Blane::findOrFail($blaneId);
            $dateRules = ['required', 'date'];
            if ($blane->start_date) {
                $dateRules[] = 'after_or_equal:' . $blane->start_date->format('Y-m-d');
            }
            if ($blane->expiration_date) {
                $dateRules[] = 'before_or_equal:' . $blane->expiration_date->format('Y-m-d');
            }

            $validatedData = $request->validate([
                'blane_id' => 'required|integer|exists:blanes,id',
                'name' => 'required|string',
                'email' => 'required|email',
                'date' => $dateRules,
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
                'confirm_exceed' => 'nullable|boolean',
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
            if ($blane->nombre_max_reservation >= $quantity) {
                $blane->nombre_max_reservation -= $quantity;
                $blane->save();
            }

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
                    'vendor_override' => ($exceedsDailyLimit || $exceedsSlotLimit),
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
                'message' => 'Failed to create Reservation',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified Reservation (Vendor version with override capability).
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $reservation = Reservation::find($id);

            if (!$reservation) {
                return response()->json(['message' => 'Reservation not found'], 404);
            }

            $blaneId = $request->input('blane_id', $reservation->blane_id);
            $blane = Blane::findOrFail($blaneId);
            $reservationType = $blane->type_time ?? 'time';
            $dateRules = ['required', 'date'];
            if ($blane->start_date) {
                $dateRules[] = 'after_or_equal:' . $blane->start_date->format('Y-m-d');
            }
            if ($blane->expiration_date) {
                $dateRules[] = 'before_or_equal:' . $blane->expiration_date->format('Y-m-d');
            }

            $validatedData = $request->validate([
                'blane_id' => 'required|integer|exists:blanes,id',
                'name' => 'required|string',
                'email' => 'required|email',
                'date' => $dateRules,
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
                'confirm_exceed' => 'nullable|boolean',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        try {
            DB::beginTransaction();

            $blane = Blane::find($validatedData['blane_id']);

            $newDate = $validatedData['date'];
            $oldDate = $reservation->date;
            $newQuantity = $validatedData['quantity'] ?? $reservation->quantity;
            $oldQuantity = $reservation->quantity;
            $confirmExceed = $request->input('confirm_exceed', false);
            $exceedsDailyLimit = false;
            $exceedsSlotLimit = false;
            $slotAvailability = null;

            if ($newDate !== $oldDate || $newQuantity !== $oldQuantity) {
                $dailyReservationsExcludingThis = Reservation::where('blane_id', $blane->id)
                    ->whereDate('date', $newDate)
                    ->where('id', '!=', $reservation->id)
                    ->where('status', '!=', 'cancelled')
                    ->sum('quantity');

                $dailyAvailability = $blane->availability_per_day !== null
                    ? max(0, $blane->availability_per_day - $dailyReservationsExcludingThis)
                    : 9999;

                $exceedsDailyLimit = $newQuantity > $dailyAvailability && $blane->availability_per_day !== null;

                if ($reservationType === 'time') {
                    $time = $validatedData['time'] ?? $reservation->time;
                    $currentSlotReservations = Reservation::where('blane_id', $blane->id)
                        ->where('date', $newDate)
                        ->where('time', $time)
                        ->where('id', '!=', $reservation->id)
                        ->where('status', '!=', 'cancelled')
                        ->sum('quantity');

                    $maxReservationsPerSlot = $blane->max_reservation_par_creneau ?? 3;
                    $slotAvailability = max(0, $maxReservationsPerSlot - $currentSlotReservations);
                    $exceedsSlotLimit = $newQuantity > $slotAvailability;
                } else {
                    $endDate = $validatedData['end_date'] ?? $reservation->end_date;
                    $currentReservations = Reservation::where('blane_id', $blane->id)
                        ->where('date', $newDate)
                        ->where('end_date', $endDate)
                        ->where('id', '!=', $reservation->id)
                        ->where('status', '!=', 'cancelled')
                        ->sum('quantity');

                    $maxReservationsPerSlot = $blane->max_reservation_par_creneau ?? 3;
                    $slotAvailability = max(0, $maxReservationsPerSlot - $currentReservations);
                    $exceedsSlotLimit = $newQuantity > $slotAvailability;
                }

                if (($exceedsDailyLimit || $exceedsSlotLimit) && !$confirmExceed) {
                    return response()->json([
                        'requires_confirmation' => true,
                        'message' => 'Updated quantity exceeds availability for this slot/date. Do you still want to confirm?',
                        'availability' => [
                            'slot_available' => $slotAvailability,
                            'daily_available' => $dailyAvailability,
                            'requested_quantity' => $newQuantity,
                            'current_quantity' => $oldQuantity,
                            'exceeds_slot_limit' => $exceedsSlotLimit,
                            'exceeds_daily_limit' => $exceedsDailyLimit,
                        ]
                    ], 422);
                }
            }

            $quantityDiff = $newQuantity - $oldQuantity;
            if ($quantityDiff != 0) {
                if ($quantityDiff > 0 && $blane->nombre_max_reservation >= $quantityDiff) {
                    $blane->nombre_max_reservation -= $quantityDiff;
                    $blane->save();
                } elseif ($quantityDiff < 0) {
                    $blane->nombre_max_reservation += abs($quantityDiff);
                    $blane->save();
                }
            }

            $customer = Customers::where('phone', $validatedData['phone'])->first();
            if ($customer) {
                $customer->update([
                    'name' => $validatedData['name'],
                    'email' => $validatedData['email'],
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

            if (isset($validatedData['date'])) {
                $date = \Carbon\Carbon::parse($validatedData['date'])->setTimezone(config('app.timezone'));
                $validatedData['date'] = $date->format('Y-m-d H:i:s');
            }

            if (isset($validatedData['end_date'])) {
                $endDate = \Carbon\Carbon::parse($validatedData['end_date'])->setTimezone(config('app.timezone'));
                $validatedData['end_date'] = $endDate->format('Y-m-d H:i:s');
            }

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
                'vendor_override' => ($exceedsDailyLimit || $exceedsSlotLimit),
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
     * Get available time slots for vendor (no restrictions).
     *
     * @param Request $request
     * @param string $slug
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
                        'available' => true,
                        'currentReservations' => $current,
                        'maxReservations' => $maxReservationsPerSlot,
                        'remainingCapacity' => max(0, $maxReservationsPerSlot - $current),
                        'dailyAvailability' => $dailyAvailability,
                        'dailyLimit' => $dailyLimit,
                        'vendor_mode' => true,
                    ];
                }

                return response()->json([
                    'type' => 'time',
                    'data' => $timeSlots,
                    'daily_availability' => [
                        'remaining' => $dailyAvailability,
                        'limit' => $dailyLimit,
                        'has_daily_limit' => $dailyLimit !== null
                    ],
                    'vendor_mode' => true,
                ], 200);
            } else {
                $currentReservations = Reservation::where('blane_id', $blane->id)
                    ->where('date', $date)
                    ->where('status', '!=', 'cancelled')
                    ->sum('quantity');

                $remainingCapacity = max(0, $maxReservationsPerSlot - $currentReservations);
                $percentageFull = $maxReservationsPerSlot > 0
                    ? round(($currentReservations / $maxReservationsPerSlot) * 100)
                    : 0;

                return response()->json([
                    'type' => 'date',
                    'data' => [
                        'date' => $date,
                        'available' => true,
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
                    ],
                    'vendor_mode' => true,
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
}

