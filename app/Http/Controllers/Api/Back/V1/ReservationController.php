<?php

namespace App\Http\Controllers\Api\Back\V1;

use Illuminate\Http\Request;
use App\Models\Reservation;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Api\BaseController;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\Back\V1\ReservationResource;
use App\Http\Resources\Back\V1\OrderResource;
use App\Models\Customers;
use App\Models\Blane;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Mail;
use App\Mail\ReservationConfirmation;
use App\Mail\ReservationUpdated;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Schema(
 *     schema="BackReservation",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="NUM_RES", type="string", example="RES-AB123456"),
 *     @OA\Property(property="blane_id", type="integer", example=1),
 *     @OA\Property(property="customers_id", type="integer", example=1),
 *     @OA\Property(property="vendor_id", type="integer", example=1),
 *     @OA\Property(property="date", type="string", format="date", example="2024-03-15"),
 *     @OA\Property(property="end_date", type="string", format="date", example="2024-03-16"),
 *     @OA\Property(property="time", type="string", format="time", example="14:00"),
 *     @OA\Property(property="number_persons", type="integer", example=2),
 *     @OA\Property(property="quantity", type="integer", example=1),
 *     @OA\Property(property="total_price", type="number", format="float", example=299.99),
 *     @OA\Property(property="partiel_price", type="number", format="float", example=100.00),
 *     @OA\Property(property="payment_method", type="string", enum={"cash", "online", "partiel"}, example="online"),
 *     @OA\Property(property="status", type="string", example="confirmed"),
 *     @OA\Property(property="source", type="string", enum={"web", "mobile", "agent"}, example="web"),
 *     @OA\Property(property="comments", type="string", example="Special request"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="BackReservationCreateRequest",
 *     type="object",
 *     required={"blane_id", "name", "email", "date", "phone", "city", "total_price", "status"},
 *     @OA\Property(property="blane_id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="John Doe"),
 *     @OA\Property(property="email", type="string", format="email", example="john@example.com"),
 *     @OA\Property(property="date", type="string", format="date", example="2024-03-15"),
 *     @OA\Property(property="phone", type="string", maxLength=20, example="+212600000000"),
 *     @OA\Property(property="city", type="string", example="Casablanca"),
 *     @OA\Property(property="time", type="string", format="time", example="14:00"),
 *     @OA\Property(property="end_date", type="string", format="date", example="2024-03-16"),
 *     @OA\Property(property="total_price", type="number", format="float", example=299.99),
 *     @OA\Property(property="partiel_price", type="number", format="float", example=100.00),
 *     @OA\Property(property="number_persons", type="integer", example=2),
 *     @OA\Property(property="payment_method", type="string", enum={"cash", "online", "partiel"}, example="online"),
 *     @OA\Property(property="quantity", type="integer", example=1),
 *     @OA\Property(property="status", type="string", enum={"confirmed", "pending", "shipped", "cancelled", "paid", "failed"}, example="pending"),
 *     @OA\Property(property="comments", type="string", example="Special request"),
 *     @OA\Property(property="source", type="string", enum={"web", "mobile", "agent"}, example="web")
 * )
 *
 * @OA\Schema(
 *     schema="VendorReservationsOrdersResponse",
 *     type="object",
 *     @OA\Property(property="total_reservations", type="integer", example=50),
 *     @OA\Property(property="total_orders", type="integer", example=30),
 *     @OA\Property(property="total_revenue", type="number", format="float", example=15000.00),
 *     @OA\Property(property="average_basket", type="number", format="float", example=187.50),
 *     @OA\Property(property="reservation_status_distribution", type="array", @OA\Items(
 *         type="object",
 *         @OA\Property(property="status", type="string"),
 *         @OA\Property(property="count", type="integer"),
 *         @OA\Property(property="percentage", type="number")
 *     )),
 *     @OA\Property(property="past_reservations", type="array", @OA\Items(ref="#/components/schemas/BackReservation")),
 *     @OA\Property(property="future_reservations", type="array", @OA\Items(ref="#/components/schemas/BackReservation")),
 *     @OA\Property(property="orders", type="array", @OA\Items(ref="#/components/schemas/BackOrder"))
 * )
 */
class ReservationController extends BaseController
{
    /**
     * Display a listing of the Reservations.
     *
     * @OA\Get(
     *     path="/back/v1/reservations",
     *     tags={"Back - Reservations"},
     *     summary="List all reservations",
     *     description="Get a paginated list of reservations with optional filtering, sorting, and includes",
     *     operationId="backReservationsIndex",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="include", in="query", description="Comma-separated relationships (blane,user,customer)", @OA\Schema(type="string")),
     *     @OA\Parameter(name="paginationSize", in="query", description="Items per page", @OA\Schema(type="integer", default=10)),
     *     @OA\Parameter(name="sort_by", in="query", @OA\Schema(type="string", enum={"created_at", "date", "time", "status", "source"})),
     *     @OA\Parameter(name="sort_order", in="query", @OA\Schema(type="string", enum={"asc", "desc"})),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="blane_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="date", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="email", in="query", @OA\Schema(type="string", format="email")),
     *     @OA\Parameter(name="source", in="query", @OA\Schema(type="string", enum={"web", "mobile", "agent"})),
     *     @OA\Response(
     *         response=200,
     *         description="Reservations retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/BackReservation")),
     *             @OA\Property(property="links", ref="#/components/schemas/PaginationLinks"),
     *             @OA\Property(property="meta", ref="#/components/schemas/PaginationMeta")
     *         )
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
                'include' => [
                    'nullable',
                    'string',
                    function ($attribute, $value, $fail) {
                        $validIncludes = ['blane', 'user', 'customer']; // Valid relationships
                        $includes = explode(',', $value);
                        foreach ($includes as $include) {
                            if (!in_array($include, $validIncludes)) {
                                $fail('The selected ' . $attribute . ' is invalid.');
                            }
                        }
                    },
                ],
                'paginationSize' => 'nullable|integer|min:1',
                'sort_by' => 'nullable|string|in:created_at,date,time,status,source',
                'sort_order' => 'nullable|string|in:asc,desc',
                'search' => 'nullable|string',
                'status' => 'nullable|string',
                'blane_id' => 'nullable|integer',
                'date' => 'nullable|date',
                'email' => 'nullable|email',
                'source' => 'nullable|string|in:web,mobile,agent',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        $query = Reservation::query();

        $this->applyFilters($request, $query);
        $this->applySearch($request, $query);
        $this->applySorting($request, $query);

        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $query->with($includes);
        }

        if ($request->has('email') && !$request->has('include')) {
            $query->with('customer');
        }

        $paginationSize = $request->input('paginationSize', 10);
        $reservations = $query->paginate($paginationSize);

        return ReservationResource::collection($reservations);
    }

    /**
     * Display the specified Reservation.
     *
     * @OA\Get(
     *     path="/back/v1/reservations/{id}",
     *     tags={"Back - Reservations"},
     *     summary="Get a specific reservation",
     *     description="Retrieve a single reservation by ID",
     *     operationId="backReservationsShow",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="include", in="query", description="Comma-separated relationships (blane,user,customer)", @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="Reservation retrieved successfully",
     *         @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/BackReservation"))
     *     ),
     *     @OA\Response(response=400, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")),
     *     @OA\Response(response=404, description="Reservation not found", @OA\JsonContent(ref="#/components/schemas/NotFoundResponse"))
     * )
     *
     * @param int $id
     * @param Request $request
     */
    public function show($id, Request $request)
    {
        try {
            $request->validate([
                'include' => [
                    'nullable',
                    'string',
                    function ($attribute, $value, $fail) {
                        $validIncludes = ['blane', 'user', 'customer']; // Valid relationships
                        $includes = explode(',', $value);
                        foreach ($includes as $include) {
                            if (!in_array($include, $validIncludes)) {
                                $fail('The selected ' . $attribute . ' is invalid.');
                            }
                        }
                    },
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        $query = Reservation::query();

        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $query->with($includes);
        }

        $reservation = $query->find($id);

        if (!$reservation) {
            return response()->json(['message' => 'Reservation not found'], 404);
        }

        return new ReservationResource($reservation);
    }

    /**
     * Check daily availability for a blane
     *
     * @param int $blaneId
     * @param string $date
     * @param int $quantity
     * @return bool
     */
    private function checkDailyAvailability($blaneId, $date, $quantity): bool
    {
        $blane = Blane::findOrFail($blaneId);

        if ($blane->availability_per_day === null) {
            return true;
        }

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
    private function getRemainingDailyAvailability($blaneId, $date): int
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
     * Store a newly created Reservation.
     *
     * @OA\Post(
     *     path="/back/v1/reservations",
     *     tags={"Back - Reservations"},
     *     summary="Create a new reservation",
     *     description="Create a new reservation (admin/agent creation)",
     *     operationId="backReservationsStore",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/BackReservationCreateRequest")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Reservation created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Reservation created successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/BackReservation")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Validation error or daily limit reached", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
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
                'blane_id' => 'required|integer|exists:blanes,id',
                'name' => 'required|string',
                'email' => 'required|email',
                'date' => 'required|date|after_or_equal:today',
                'phone' => 'required|string|max:20',
                'city' => 'required|string',
                'time' => 'nullable|date_format:H:i',
                'end_date' => 'nullable|date|after_or_equal:date',
                'total_price' => 'required|numeric|min:0',
                'partiel_price' => 'nullable|numeric|min:0',
                'number_persons' => 'nullable|integer|min:1',
                'payment_method' => 'nullable|string|in:cash',
                'online',
                'partiel',
                'quantity' => 'nullable|integer|min:1',
                'status' => 'required|string|in:confirmed',
                'pending',
                'shipped',
                'cancelled',
                'paid',
                'failed',
                'comments' => 'nullable|string',
                'source' => 'nullable|string|in:web,mobile,agent',
            ]);
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
                    'message' => 'Daily reservation limit reached. Only ' . $remaining . ' spots available for this date.'
                ], 400);
            }

            if ($blane->nombre_max_reservation < $validatedData['quantity']) {
                return response()->json(['message' => 'Blane is full'], 400);
            }
            $blane->nombre_max_reservation -= $validatedData['quantity'];
            $blane->save();

            $validatedData['NUM_RES'] = $this->generateUniqueReservationCode();

            $customer = Customers::create([
                'name' => $validatedData['name'],
                'email' => $validatedData['email'],
                'phone' => $validatedData['phone'],
                'city' => $validatedData['city'],
            ]);

            $validatedData['customers_id'] = $customer->id;

            $reservation = Reservation::create($validatedData);
            try {
                Mail::to($validatedData['email'])->send(new ReservationConfirmation($reservation));
            } catch (\Exception $e) {
                Log::error('Failed to send reservation confirmation email: ' . $e->getMessage());

            }

            DB::commit();

            return response()->json([
                'message' => 'Reservation created successfully',
                'data' => new ReservationResource($reservation),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create Reservation: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to create Reservation',
            ], 500);
        }
    }

    /**
     * Generate a unique reservation code.
     *
     * @return string
     */
    private function generateUniqueReservationCode(): string
    {
        do {
            $code = 'RES-' . substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 2) . str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (Reservation::where('NUM_RES', $code)->exists());

        return $code;
    }

    /**
     * Update the specified Reservation.
     *
     * @OA\Put(
     *     path="/back/v1/reservations/{id}",
     *     tags={"Back - Reservations"},
     *     summary="Update a reservation",
     *     description="Update an existing reservation",
     *     operationId="backReservationsUpdate",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/BackReservationCreateRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Reservation updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Reservation updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/BackReservation")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Validation error or daily limit reached", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")),
     *     @OA\Response(response=404, description="Reservation not found", @OA\JsonContent(ref="#/components/schemas/NotFoundResponse")),
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
                'blane_id' => 'required|integer|exists:blanes,id',
                'name' => 'required|string',
                'email' => 'required|email',
                'date' => 'required|date|after_or_equal:today',
                'phone' => 'required|string|max:20',
                'city' => 'required|string',
                'time' => 'nullable|date_format:H:i',
                'end_date' => 'nullable|date|after_or_equal:date',
                'number_persons' => 'required|integer|min:1',
                'total_price' => 'required|numeric|min:0',
                'partiel_price' => 'nullable|numeric|min:0',
                'payment_method' => 'nullable|string|in:cash,online,partiel',
                'quantity' => 'nullable|integer',
                'status' => 'required|string|in:confirmed,pending,shipped,cancelled,paid,failed',
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

            $customer = Customers::where('id', $reservation->customers_id)->first();
            if ($customer) {
                $customer->update([
                    'name' => $validatedData['name'],
                    'email' => $validatedData['email'],
                    'city' => $validatedData['city'],
                    'phone' => $validatedData['phone'],
                ]);
            }
            $validatedData['customers_id'] = $customer->id;
            $reservation->update($validatedData);

            try {
                Mail::to($validatedData['email'])->send(new ReservationUpdated($reservation));
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
            ], 500);
        }
    }

    /**
     * Remove the specified Reservation.
     *
     * @OA\Delete(
     *     path="/back/v1/reservations/{id}",
     *     tags={"Back - Reservations"},
     *     summary="Delete a reservation",
     *     description="Delete a reservation by ID",
     *     operationId="backReservationsDestroy",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Reservation deleted successfully"),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")),
     *     @OA\Response(response=404, description="Reservation not found", @OA\JsonContent(ref="#/components/schemas/NotFoundResponse")),
     *     @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
     * )
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

        if ($request->has('date')) {
            $query->where('date', $request->input('date'));
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
     * Change the status of a reservation
     *
     * @OA\Patch(
     *     path="/back/v1/reservations/{id}/status",
     *     tags={"Back - Reservations"},
     *     summary="Update reservation status",
     *     description="Change the status of a reservation",
     *     operationId="backReservationsUpdateStatus",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"status"},
     *             @OA\Property(property="status", type="string", example="confirmed", description="Status: waiting, client_confirmed, retailer_confirmed, admin_confirmed, client_cancelled, retailer_cancelled, admin_cancelled, confirmed, pending, shipped, cancelled, paid, failed")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Reservation status updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Reservation status updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/BackReservation")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")),
     *     @OA\Response(response=404, description="Reservation not found", @OA\JsonContent(ref="#/components/schemas/NotFoundResponse"))
     * )
     *
     * @param Request $request
     */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        $reservation = Reservation::find($id);

        if (!$reservation) {
            return response()->json(['message' => 'Reservation not found'], 404);
        }
        $statusMapping = [
            'Waiting' => 'waiting',
            'Client Confirmed' => 'client_confirmed',
            'Retailer Confirmed' => 'retailer_confirmed',
            'Admin Confirmed' => 'admin_confirmed',
            'Client Cancelled' => 'client_cancelled',
            'Retailer Cancelled' => 'retailer_cancelled',
            'Admin Cancelled' => 'admin_cancelled',
            'Cancelled (Client didn\'t Respond)' => 'cancelled_client_no_response',
            'Cancelled (Retailer didn\'t Respond)' => 'cancelled_retailer_no_response',
            'Escalated to Admin (Retailer didn\'t Respond)' => 'escalated_admin',
            'Admin Give up Waiting' => 'admin_give_up',
        ];

        $request->validate([
            'status' => [
                'required',
                'string',
                Rule::in(array_merge(
                    array_keys($statusMapping),
                    array_values($statusMapping),
                    [
                        'confirmed',
                        'pending',
                        'shipped',
                        'cancelled',
                        'paid',
                        'failed'
                    ]
                ))
            ],
        ]);

        $status = $request->input('status');

        if (array_key_exists($status, $statusMapping)) {
            $status = $statusMapping[$status];
        }

        $reservation->status = $status;
        $reservation->save();

        return response()->json([
            'message' => 'Reservation status updated successfully',
            'data' => new ReservationResource($reservation)
        ], 200);
    }

    /**
     * List reservations (alternative endpoint)
     *
     * @OA\Get(
     *     path="/back/v1/reservations/list",
     *     tags={"Back - Reservations"},
     *     summary="List reservations with blane images",
     *     description="Get a paginated list of reservations with optional blane image includes",
     *     operationId="backReservationsList",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="include", in="query", description="Comma-separated relationships (blane,user,customer,blaneImage)", @OA\Schema(type="string")),
     *     @OA\Parameter(name="paginationSize", in="query", @OA\Schema(type="integer", default=10)),
     *     @OA\Parameter(name="sort_by", in="query", @OA\Schema(type="string", enum={"created_at", "date", "time", "status", "source"})),
     *     @OA\Parameter(name="sort_order", in="query", @OA\Schema(type="string", enum={"asc", "desc"})),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="blane_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="date", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="email", in="query", @OA\Schema(type="string", format="email")),
     *     @OA\Parameter(name="source", in="query", @OA\Schema(type="string", enum={"web", "mobile", "agent"})),
     *     @OA\Response(
     *         response=200,
     *         description="Reservations retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/BackReservation")),
     *             @OA\Property(property="links", ref="#/components/schemas/PaginationLinks"),
     *             @OA\Property(property="meta", ref="#/components/schemas/PaginationMeta")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse"))
     * )
     */
    public function reservationlist(Request $request)
    {
        try {
            $request->validate([
                'include' => [
                    'nullable',
                    'string',
                    function ($attribute, $value, $fail) {
                        $validIncludes = ['blane', 'user', 'customer', 'blaneImage']; // Added blaneImage
                        $includes = explode(',', $value);
                        foreach ($includes as $include) {
                            if (!in_array($include, $validIncludes)) {
                                $fail('The selected ' . $attribute . ' is invalid.');
                            }
                        }
                    },
                ],
                'paginationSize' => 'nullable|integer|min:1',
                'sort_by' => 'nullable|string|in:created_at,date,time,status,source',
                'sort_order' => 'nullable|string|in:asc,desc',
                'search' => 'nullable|string',
                'status' => 'nullable|string',
                'blane_id' => 'nullable|integer',
                'date' => 'nullable|date',
                'email' => 'nullable|email',
                'source' => 'nullable|string|in:web,mobile,agent',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        $query = Reservation::query();

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

        if ($request->has('email') && !$request->has('include')) {
            $query->with('customer');
        }

        $paginationSize = $request->input('paginationSize', 10);
        $reservations = $query->paginate($paginationSize);

        return ReservationResource::collection($reservations);
    }

    /**
     * Get user's reservations and orders
     *
     * @OA\Get(
     *     path="/back/v1/reservations-orders",
     *     tags={"Back - Reservations"},
     *     summary="Get user reservations and orders",
     *     description="Get paginated past and future reservations/orders for the authenticated user",
     *     operationId="backGetReservationsAndOrders",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="include", in="query", description="Comma-separated relationships (blane,user,customer,shippingDetails,blaneImage,ratings)", @OA\Schema(type="string")),
     *     @OA\Parameter(name="paginationSize", in="query", @OA\Schema(type="integer", default=10)),
     *     @OA\Parameter(name="sort_by", in="query", @OA\Schema(type="string", enum={"created_at", "date", "time", "status", "total_price", "source"})),
     *     @OA\Parameter(name="sort_order", in="query", @OA\Schema(type="string", enum={"asc", "desc"})),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="blane_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="date", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="email", in="query", @OA\Schema(type="string", format="email")),
     *     @OA\Parameter(name="source", in="query", @OA\Schema(type="string", enum={"web", "mobile", "agent"})),
     *     @OA\Response(
     *         response=200,
     *         description="Reservations and orders retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="past_reservations", type="array", @OA\Items(ref="#/components/schemas/BackReservation")),
     *             @OA\Property(property="past_reservations_meta", ref="#/components/schemas/PaginationMeta"),
     *             @OA\Property(property="future_reservations", type="array", @OA\Items(ref="#/components/schemas/BackReservation")),
     *             @OA\Property(property="future_reservations_meta", ref="#/components/schemas/PaginationMeta"),
     *             @OA\Property(property="past_orders", type="array", @OA\Items(ref="#/components/schemas/BackOrder")),
     *             @OA\Property(property="past_orders_meta", ref="#/components/schemas/PaginationMeta"),
     *             @OA\Property(property="future_orders", type="array", @OA\Items(ref="#/components/schemas/BackOrder")),
     *             @OA\Property(property="future_orders_meta", ref="#/components/schemas/PaginationMeta")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse"))
     * )
     */
    public function getReservationsAndOrders(Request $request)
    {

        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'error' => 'Unauthenticated. Please log in first.',
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'include' => [
                'nullable',
                'string',
                function ($attribute, $value, $fail) {
                    $validIncludes = ['blane', 'user', 'customer', 'shippingDetails', 'blaneImage', 'ratings'];
                    $includes = explode(',', $value);
                    foreach ($includes as $include) {
                        if (!in_array($include, $validIncludes)) {
                            $fail('The selected ' . $attribute . ' is invalid.');
                        }
                    }
                },
            ],
            'paginationSize' => 'nullable|integer|min:1',
            'sort_by' => 'nullable|string|in:created_at,date,time,status,total_price,source',
            'sort_order' => 'nullable|string|in:asc,desc',
            'search' => 'nullable|string',
            'status' => 'nullable|string',
            'blane_id' => 'nullable|integer',
            'date' => 'nullable|date',
            'email' => 'nullable|email',
            'source' => 'nullable|string|in:web,mobile,agent',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // Prepare includes
        $includes = [];
        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $includes = array_map(function ($include) {
                if ($include === 'blaneImage') {
                    return 'blane.blaneImages';
                } elseif ($include === 'ratings') {
                    return 'blane.ratings';
                }
                return $include;
            }, $includes);
        }

        $paginationSize = $request->input('paginationSize', 10);
        $today = Carbon::today()->toDateString();
        $email = $request->input('email') ?? $user->email; // Default to authenticated user's email

        // Define ratings filter closure if email is provided
        $ratingsFilter = $email && in_array('blane.ratings', $includes)
            ? [
                'blane.ratings' => function ($query) use ($email) {
                    $query->whereHas('user', function ($q) use ($email) {
                        $q->where('email', $email);
                    });
                }
            ]
            : [];

        $queryIncludes = array_diff($includes, ['blane.ratings']);
        if ($ratingsFilter) {
            $queryIncludes = array_merge($queryIncludes, $ratingsFilter);
        }

        $pastReservationQuery = Reservation::query()->where('date', '<', $today);

        if ($request->has('status')) {
            $pastReservationQuery->where('status', $request->input('status'));
        }

        if ($request->has('blane_id')) {
            $pastReservationQuery->where('blane_id', $request->input('blane_id'));
        }

        if ($request->has('date')) {
            $pastReservationQuery->where('date', $request->input('date'));
        }

        if ($request->has('source')) {
            $pastReservationQuery->where('source', $request->input('source'));
        }
        $pastReservationQuery->whereHas('customer', function ($q) use ($email) {
            $q->where('email', 'like', "%$email%");
        });

        if ($request->has('search')) {
            $search = $request->input('search');
            $pastReservationQuery->where(function ($q) use ($search) {
                $q->where('comments', 'like', "%$search%")
                    ->orWhere('status', 'like', "%$search%")
                    ->orWhere('source', 'like', "%$search%");
            });
        }

        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $allowedReservationSortBy = ['created_at', 'date', 'time', 'status', 'source'];
        if (in_array($sortBy, $allowedReservationSortBy)) {
            $pastReservationQuery->orderBy($sortBy, $sortOrder);
        } else {
            $pastReservationQuery->orderBy('created_at', 'desc');
        }

        $pastReservationQuery->with($queryIncludes);

        if (!in_array('customer', $includes)) {
            $pastReservationQuery->with('customer');
        }

        $pastReservations = $pastReservationQuery->paginate($paginationSize);

        $futureReservationQuery = Reservation::query()->where('date', '>=', $today);

        if ($request->has('status')) {
            $futureReservationQuery->where('status', $request->input('status'));
        }

        if ($request->has('blane_id')) {
            $futureReservationQuery->where('blane_id', $request->input('blane_id'));
        }

        if ($request->has('date')) {
            $futureReservationQuery->where('date', $request->input('date'));
        }

        if ($request->has('source')) {
            $futureReservationQuery->where('source', $request->input('source'));
        }

        $futureReservationQuery->whereHas('customer', function ($q) use ($email) {
            $q->where('email', 'like', "%$email%");
        });

        if ($request->has('search')) {
            $search = $request->input('search');
            $futureReservationQuery->where(function ($q) use ($search) {
                $q->where('comments', 'like', "%$search%")
                    ->orWhere('status', 'like', "%$search%")
                    ->orWhere('source', 'like', "%$search%");
            });
        }

        if (in_array($sortBy, $allowedReservationSortBy)) {
            $futureReservationQuery->orderBy($sortBy, $sortOrder);
        } else {
            $futureReservationQuery->orderBy('created_at', 'desc');
        }

        $futureReservationQuery->with($queryIncludes);

        if (!in_array('customer', $includes)) {
            $futureReservationQuery->with('customer');
        }

        $futureReservations = $futureReservationQuery->paginate($paginationSize);

        $pastOrderQuery = Order::query()->whereNotIn('status', ['pending', 'confirmed']);

        if ($request->has('status')) {
            $pastOrderQuery->where('status', $request->input('status'));
        }

        if ($request->has('blane_id')) {
            $pastOrderQuery->where('blane_id', $request->input('blane_id'));
        }

        if ($request->has('date')) {
            $pastOrderQuery->whereDate('created_at', $request->input('date'));
        }

        $pastOrderQuery->whereHas('customer', function ($q) use ($email) {
            $q->where('email', 'like', "%$email%");
        });

        if ($request->has('search')) {
            $search = $request->input('search');
            $pastOrderQuery->where('status', 'like', "%$search%");
        }

        $allowedOrderSortBy = ['created_at', 'total_price', 'status'];
        if (in_array($sortBy, $allowedOrderSortBy)) {
            $pastOrderQuery->orderBy($sortBy, $sortOrder);
        } else {
            $pastOrderQuery->orderBy('created_at', 'desc');
        }

        $pastOrderQuery->with($queryIncludes);

        if (!in_array('customer', $includes)) {
            $pastOrderQuery->with('customer');
        }

        $pastOrders = $pastOrderQuery->paginate($paginationSize);

        $futureOrderQuery = Order::query()->whereIn('status', ['pending', 'confirmed']);

        if ($request->has('status')) {
            $futureOrderQuery->where('status', $request->input('status'));
        }

        if ($request->has('blane_id')) {
            $futureOrderQuery->where('blane_id', $request->input('blane_id'));
        }

        if ($request->has('date')) {
            $futureOrderQuery->whereDate('created_at', $request->input('date'));
        }

        $futureOrderQuery->whereHas('customer', function ($q) use ($email) {
            $q->where('email', 'like', "%$email%");
        });

        if ($request->has('search')) {
            $search = $request->input('search');
            $futureOrderQuery->where('status', 'like', "%$search%");
        }

        if (in_array($sortBy, $allowedOrderSortBy)) {
            $futureOrderQuery->orderBy($sortBy, $sortOrder);
        } else {
            $futureOrderQuery->orderBy('created_at', 'desc');
        }

        $futureOrderQuery->with($queryIncludes);

        if (!in_array('customer', $includes)) {
            $futureOrderQuery->with('customer');
        }

        $futureOrders = $futureOrderQuery->paginate($paginationSize);

        $getPaginationMeta = function ($paginator) {
            return [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'has_more_pages' => $paginator->hasMorePages(),
            ];
        };

        return response()->json([
            'past_reservations' => ReservationResource::collection($pastReservations),
            'past_reservations_meta' => $getPaginationMeta($pastReservations),
            'future_reservations' => ReservationResource::collection($futureReservations),
            'future_reservations_meta' => $getPaginationMeta($futureReservations),
            'past_orders' => OrderResource::collection($pastOrders),
            'past_orders_meta' => $getPaginationMeta($pastOrders),
            'future_orders' => OrderResource::collection($futureOrders),
            'future_orders_meta' => $getPaginationMeta($futureOrders),
        ]);
    }

    /**
     * Get vendor's reservations and orders with statistics
     *
     * @OA\Get(
     *     path="/back/v1/vendor/reservations-orders",
     *     tags={"Back - Reservations"},
     *     summary="Get vendor reservations and orders",
     *     description="Get vendor's reservations, orders with statistics including revenue and status distribution",
     *     operationId="backGetVendorReservationsAndOrders",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="commerce_name", in="query", description="Vendor commerce name", @OA\Schema(type="string")),
     *     @OA\Parameter(name="vendor_id", in="query", description="Vendor user ID", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="include", in="query", description="Comma-separated relationships (blane,user,customer,shippingDetails,blaneImage,ratings)", @OA\Schema(type="string")),
     *     @OA\Parameter(name="paginationSize", in="query", @OA\Schema(type="integer", default=10)),
     *     @OA\Parameter(name="sort_by", in="query", @OA\Schema(type="string", enum={"created_at", "date", "time", "status", "total_price", "source"})),
     *     @OA\Parameter(name="sort_order", in="query", @OA\Schema(type="string", enum={"asc", "desc"})),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="blane_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="date", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="email", in="query", @OA\Schema(type="string", format="email")),
     *     @OA\Parameter(name="include_expired", in="query", @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="period", in="query", @OA\Schema(type="string", enum={"1_month", "3_months", "6_months"})),
     *     @OA\Parameter(name="source", in="query", @OA\Schema(type="string", enum={"web", "mobile", "agent"})),
     *     @OA\Response(
     *         response=200,
     *         description="Vendor data retrieved successfully",
     *         @OA\JsonContent(ref="#/components/schemas/VendorReservationsOrdersResponse")
     *     ),
     *     @OA\Response(response=400, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")),
     *     @OA\Response(response=422, description="Vendor identification required", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse"))
     * )
     */
    public function getVendorReservationsAndOrders(Request $request)
    {
        try {
            $request->validate([
                'commerce_name' => 'nullable|string', // Made optional to support vendor_id
                'vendor_id' => 'nullable|integer|exists:users,id', // New parameter
                'include' => [
                    'nullable',
                    'string',
                    function ($attribute, $value, $fail) {
                        $validIncludes = ['blane', 'user', 'customer', 'shippingDetails', 'blaneImage', 'ratings'];
                        $includes = explode(',', $value);
                        foreach ($includes as $include) {
                            if (!in_array($include, $validIncludes)) {
                                $fail('The selected ' . $attribute . ' is invalid.');
                            }
                        }
                    },
                ],
                'paginationSize' => 'nullable|integer|min:1',
                'sort_by' => 'nullable|string|in:created_at,date,time,status,total_price,source',
                'sort_order' => 'nullable|string|in:asc,desc',
                'search' => 'nullable|string',
                'status' => 'nullable|string',
                'blane_id' => 'nullable|integer',
                'date' => 'nullable|date',
                'email' => 'nullable|email',
                'include_expired' => 'nullable|boolean',
                'period' => 'nullable|string|in:1_month,3_months,6_months',
                'source' => 'nullable|string|in:web,mobile,agent',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        $includes = [];
        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $includes = array_map(function ($include) {
                if ($include === 'blaneImage') {
                    return 'blane.blaneImages';
                } elseif ($include === 'ratings') {
                    return 'blane.ratings';
                }
                return $include;
            }, $includes);
        }

        $paginationSize = $request->input('paginationSize', 10);
        $today = Carbon::today()->toDateString();
        $email = $request->input('email');
        $ratingsFilter = $email && in_array('blane.ratings', $includes)
            ? [
                'blane.ratings' => function ($query) use ($email) {
                    $query->whereHas('user', function ($q) use ($email) {
                        $q->where('email', $email);
                    });
                }
            ]
            : [];

        $queryIncludes = array_diff($includes, ['blane.ratings']);
        if ($ratingsFilter) {
            $queryIncludes = array_merge($queryIncludes, $ratingsFilter);
        }

        // Determine vendor - support both vendor_id (new way) and commerce_name (old way)
        $vendor = null;
        if ($request->filled('vendor_id')) {
            $vendor = \App\Models\User::whereHas('roles', function ($q) {
                $q->where('name', 'vendor');
            })->find($request->input('vendor_id'));
        } elseif ($request->filled('commerce_name')) {
            $vendor = \App\Models\User::where('company_name', $request->input('commerce_name'))
                ->whereHas('roles', function ($q) {
                    $q->where('name', 'vendor');
                })
                ->first();
        } elseif (auth()->check() && auth()->user()->hasRole('vendor')) {
            // Auto-use authenticated vendor
            $vendor = auth()->user();
        }

        if (!$vendor) {
            return response()->json([
                'status' => false,
                'code' => 422,
                'message' => 'Vendor identification required (vendor_id or commerce_name)'
            ], 422);
        }

        // Get blane IDs for this vendor - use vendor_id if available, otherwise fall back to commerce_name
        $blaneQuery = Blane::query();
        if ($vendor->id) {
            $blaneQuery->where(function ($q) use ($vendor) {
                $q->where('vendor_id', $vendor->id)
                    ->orWhere(function ($subQ) use ($vendor) {
                        $subQ->whereNull('vendor_id')
                            ->where('commerce_name', $vendor->company_name);
                    });
            });
        } else {
            $blaneQuery->where('commerce_name', $vendor->company_name);
        }

        if (!$request->input('include_expired', false)) {
            $blaneQuery->where('expiration_date', '>', Carbon::now());
        }
        $blaneIds = $blaneQuery->pluck('id')->toArray();
        $startDate = null;
        $period = $request->input('period');
        if ($period) {
            $months = [
                '1_month' => 1,
                '3_months' => 3,
                '6_months' => 6,
            ][$period];
            $startDate = Carbon::now()->subMonths($months)->startOfMonth();
        }

        // Build reservation query - prefer vendor_id filtering if available
        $pastReservationQuery = Reservation::query()
            ->where('date', '<', $today);
        
        // Use vendor_id if available, otherwise use blane_ids
        if ($vendor->id) {
            $pastReservationQuery->where(function ($q) use ($vendor, $blaneIds) {
                $q->where('vendor_id', $vendor->id)
                    ->orWhereIn('blane_id', $blaneIds);
            });
        } else {
            $pastReservationQuery->whereIn('blane_id', $blaneIds);
        }

        if ($startDate) {
            $pastReservationQuery->where('date', '>=', $startDate);
            $pastReservationQuery->where('date', '<=', Carbon::now()->endOfMonth());
        }

        if ($request->has('status')) {
            $pastReservationQuery->where('status', $request->input('status'));
        }

        if ($request->has('blane_id')) {
            $pastReservationQuery->where('blane_id', $request->input('blane_id'));
        }

        if ($request->has('date')) {
            $pastReservationQuery->where('date', $request->input('date'));
        }

        if ($request->has('email')) {
            $pastReservationQuery->whereHas('customer', function ($q) use ($email) {
                $q->where('email', 'like', "%$email%");
            });
        }

        if ($request->has('source')) {
            $pastReservationQuery->where('source', $request->input('source'));
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $pastReservationQuery->where(function ($q) use ($search) {
                $q->where('comments', 'like', "%$search%")
                    ->orWhere('status', 'like', "%$search%")
                    ->orWhere('source', 'like', "%$search%");
            });
        }

        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $allowedReservationSortBy = ['created_at', 'date', 'time', 'status', 'source'];
        if (in_array($sortBy, $allowedReservationSortBy)) {
            $pastReservationQuery->orderBy($sortBy, $sortOrder);
        } else {
            $pastReservationQuery->orderBy('created_at', 'desc');
        }

        $pastReservationQuery->with($queryIncludes);

        if ($request->has('email') && !$request->has('include')) {
            $pastReservationQuery->with('customer');
        }

        $pastReservations = $pastReservationQuery->paginate($paginationSize);

        // Build future reservation query - prefer vendor_id filtering if available
        $futureReservationQuery = Reservation::query()
            ->where('date', '>=', $today);
        
        // Use vendor_id if available, otherwise use blane_ids
        if ($vendor && $vendor->id) {
            $futureReservationQuery->where(function ($q) use ($vendor, $blaneIds) {
                $q->where('vendor_id', $vendor->id)
                    ->orWhereIn('blane_id', $blaneIds);
            });
        } else {
            $futureReservationQuery->whereIn('blane_id', $blaneIds);
        }

        if ($startDate) {
            $futureReservationQuery->where('date', '>=', $startDate);
            $futureReservationQuery->where('date', '<=', Carbon::now()->endOfMonth());
        }

        if ($request->has('status')) {
            $futureReservationQuery->where('status', $request->input('status'));
        }

        if ($request->has('blane_id')) {
            $futureReservationQuery->where('blane_id', $request->input('blane_id'));
        }

        if ($request->has('date')) {
            $futureReservationQuery->where('date', $request->input('date'));
        }

        if ($request->has('email')) {
            $futureReservationQuery->whereHas('customer', function ($q) use ($email) {
                $q->where('email', 'like', "%$email%");
            });
        }
        if ($request->has('source')) {
            $futureReservationQuery->where('source', $request->input('source'));
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $futureReservationQuery->where(function ($q) use ($search) {
                $q->where('comments', 'like', "%$search%")
                    ->orWhere('status', 'like', "%$search%")
                    ->orWhere('source', 'like', "%$search%");
            });
        }

        if (in_array($sortBy, $allowedReservationSortBy)) {
            $futureReservationQuery->orderBy($sortBy, $sortOrder);
        } else {
            $futureReservationQuery->orderBy('created_at', 'desc');
        }

        $futureReservationQuery->with($queryIncludes);

        if ($request->has('email') && !$request->has('include')) {
            $futureReservationQuery->with('customer');
        }

        $futureReservations = $futureReservationQuery->paginate($paginationSize);

        // Build order query - prefer vendor_id filtering if available
        $orderQuery = Order::query();
        
        // Use vendor_id if available, otherwise use blane_ids
        if ($vendor && $vendor->id) {
            $orderQuery->where(function ($q) use ($vendor, $blaneIds) {
                $q->where('vendor_id', $vendor->id)
                    ->orWhereIn('blane_id', $blaneIds);
            });
        } else {
            $orderQuery->whereIn('blane_id', $blaneIds);
        }

        if ($startDate) {
            $orderQuery->where('created_at', '>=', $startDate);
            $orderQuery->where('created_at', '<=', Carbon::now()->endOfMonth());
        }

        if ($request->has('status')) {
            $orderQuery->where('status', $request->input('status'));
        }

        if ($request->has('blane_id')) {
            $orderQuery->where('blane_id', $request->input('blane_id'));
        }

        if ($request->has('date')) {
            $orderQuery->whereDate('created_at', $request->input('date'));
        }

        if ($request->has('email')) {
            $orderQuery->whereHas('customer', function ($q) use ($email) {
                $q->where('email', 'like', "%$email%");
            });
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $orderQuery->where('status', 'like', "%$search%");
        }

        $allowedOrderSortBy = ['created_at', 'total_price', 'status'];
        if (in_array($sortBy, $allowedOrderSortBy)) {
            $orderQuery->orderBy($sortBy, $sortOrder);
        } else {
            $orderQuery->orderBy('created_at', 'desc');
        }

        $orderQuery->with($queryIncludes);

        $orders = $orderQuery->paginate($paginationSize);

        // Build revenue queries - prefer vendor_id filtering if available
        $orderRevenueQuery = Order::query()
            ->whereNotIn('status', ['failed', 'cancelled']);
        
        // Use vendor_id if available, otherwise use blane_ids
        if ($vendor && $vendor->id) {
            $orderRevenueQuery->where(function ($q) use ($vendor, $blaneIds) {
                $q->where('vendor_id', $vendor->id)
                    ->orWhereIn('blane_id', $blaneIds);
            });
        } else {
            $orderRevenueQuery->whereIn('blane_id', $blaneIds);
        }
        
        $orderRevenueQuery->when($startDate, function ($query) use ($startDate) {
            return $query->where('created_at', '>=', $startDate)
                ->where('created_at', '<=', Carbon::now()->endOfMonth());
        });

        $reservationRevenueQuery = Reservation::query()
            ->whereNotIn('status', ['failed', 'cancelled']);
        
        // Use vendor_id if available, otherwise use blane_ids
        if ($vendor && $vendor->id) {
            $reservationRevenueQuery->where(function ($q) use ($vendor, $blaneIds) {
                $q->where('vendor_id', $vendor->id)
                    ->orWhereIn('blane_id', $blaneIds);
            });
        } else {
            $reservationRevenueQuery->whereIn('blane_id', $blaneIds);
        }
        
        $reservationRevenueQuery->when($startDate, function ($query) use ($startDate) {
            return $query->where('created_at', '>=', $startDate)
                ->where('created_at', '<=', Carbon::now()->endOfMonth());
        });

        $orderRevenue = $orderRevenueQuery->sum('total_price');
        $reservationRevenue = $reservationRevenueQuery->sum('total_price');
        $totalRevenue = $orderRevenue + $reservationRevenue;

        $periodStats = [];
        if ($period) {
            $periods = [
                '1_month' => Carbon::now()->subMonth()->startOfMonth(),
                '3_months' => Carbon::now()->subMonths(3)->startOfMonth(),
                '6_months' => Carbon::now()->subMonths(6)->startOfMonth(),
            ];
            foreach ($periods as $periodKey => $periodStartDate) {
                $periodReservations = Reservation::query()
                    ->whereIn('blane_id', $blaneIds)
                    ->where('created_at', '>=', $periodStartDate)
                    ->where('created_at', '<=', Carbon::now()->endOfMonth())
                    ->count();

                $periodOrders = Order::query()
                    ->whereIn('blane_id', $blaneIds)
                    ->where('created_at', '>=', $periodStartDate)
                    ->where('created_at', '<=', Carbon::now()->endOfMonth())
                    ->count();

                $periodOrderRevenue = Order::query()
                    ->whereIn('blane_id', $blaneIds)
                    ->whereNotIn('status', ['failed', 'cancelled'])
                    ->where('created_at', '>=', $periodStartDate)
                    ->where('created_at', '<=', Carbon::now()->endOfMonth())
                    ->sum('total_price');

                $periodReservationRevenue = Reservation::query()
                    ->whereIn('blane_id', $blaneIds)
                    ->whereNotIn('status', ['failed', 'cancelled'])
                    ->where('created_at', '>=', $periodStartDate)
                    ->where('created_at', '<=', Carbon::now()->endOfMonth())
                    ->sum('total_price');

                $periodRevenue = $periodOrderRevenue + $periodReservationRevenue;

                $periodStats[$periodKey] = [
                    'reservations' => $periodReservations,
                    'orders' => $periodOrders,
                    'revenue' => $periodRevenue,
                ];
            }
        }

        $totalReservations = Reservation::query()
            ->whereIn('blane_id', $blaneIds)
            ->when($startDate, function ($query) use ($startDate) {
                return $query->where('created_at', '>=', $startDate)
                    ->where('created_at', '<=', Carbon::now()->endOfMonth());
            })
            ->count();

        $totalOrders = Order::query()
            ->whereIn('blane_id', $blaneIds)
            ->when($startDate, function ($query) use ($startDate) {
                return $query->where('created_at', '>=', $startDate)
                    ->where('created_at', '<=', Carbon::now()->endOfMonth());
            })
            ->count();

        $reservationStatusQuery = Reservation::query()
            ->whereIn('blane_id', $blaneIds)
            ->when($startDate, function ($query) use ($startDate) {
                return $query->where('created_at', '>=', $startDate)
                    ->where('created_at', '<=', Carbon::now()->endOfMonth());
            });

        $statusDistribution = $reservationStatusQuery
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get()
            ->map(function ($item) use ($totalReservations) {
                $percentage = $totalReservations > 0 ? round(($item->count / $totalReservations) * 100, 2) : 0;
                return [
                    'status' => $item->status,
                    'count' => $item->count,
                    'percentage' => $percentage,
                ];
            })
            ->values();

        $confirmedStatuses = [
            Reservation::STATUS_CLIENT_CONFIRMED,
            Reservation::STATUS_RETAILER_CONFIRMED,
            Reservation::STATUS_ADMIN_CONFIRMED,
            Reservation::STATUS_CONFIRMED,
        ];

        $confirmedReservationsCount = Reservation::query()
            ->whereIn('blane_id', $blaneIds)
            ->whereIn('status', $confirmedStatuses)
            ->when($startDate, function ($query) use ($startDate) {
                return $query->where('created_at', '>=', $startDate)
                    ->where('created_at', '<=', Carbon::now()->endOfMonth());
            })
            ->count();

        $blaneConfirmPercentage = $totalReservations > 0
            ? round(($confirmedReservationsCount / $totalReservations) * 100, 2)
            : 0;

        $totalBookings = $totalReservations + $totalOrders;
        $averageBasket = $totalBookings > 0
            ? round($totalRevenue / $totalBookings, 2)
            : 0;

        return response()->json([
            'total_reservations' => $totalReservations,
            'total_orders' => $totalOrders,
            'total_revenue' => $totalRevenue,
            'average_basket' => $averageBasket,
            'reservation_status_distribution' => $statusDistribution,
            'blane_confirm' => [
                'count' => $confirmedReservationsCount,
                'percentage' => $blaneConfirmPercentage,
            ],
            'period_stats' => $periodStats,
            'past_reservations' => ReservationResource::collection($pastReservations),
            'future_reservations' => ReservationResource::collection($futureReservations),
            'orders' => OrderResource::collection($orders),
        ]);
    }


    /**
     * Get vendor's pending reservations
     *
     * @OA\Get(
     *     path="/back/v1/vendor/pending-reservations",
     *     tags={"Back - Reservations"},
     *     summary="Get vendor pending reservations",
     *     description="Get pending reservations older than 48 hours for a vendor",
     *     operationId="backGetVendorPendingReservations",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="commerce_name", in="query", required=true, description="Vendor commerce name", @OA\Schema(type="string")),
     *     @OA\Parameter(name="include", in="query", description="Comma-separated relationships (blane,user,customer,blaneImage)", @OA\Schema(type="string")),
     *     @OA\Parameter(name="paginationSize", in="query", @OA\Schema(type="integer", default=10)),
     *     @OA\Parameter(name="sort_by", in="query", @OA\Schema(type="string", enum={"created_at", "date", "time", "status", "source"})),
     *     @OA\Parameter(name="sort_order", in="query", @OA\Schema(type="string", enum={"asc", "desc"})),
     *     @OA\Parameter(name="source", in="query", @OA\Schema(type="string", enum={"web", "mobile", "agent"})),
     *     @OA\Response(
     *         response=200,
     *         description="Pending reservations retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Pending reservations retrieved successfully."),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/BackReservation"))
     *         )
     *     ),
     *     @OA\Response(response=400, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")),
     *     @OA\Response(response=404, description="No blanes found for vendor", @OA\JsonContent(ref="#/components/schemas/NotFoundResponse"))
     * )
     */
    public function getVendorPendingReservations(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'commerce_name' => 'required|string',
                'include' => [
                    'nullable',
                    'string',
                    function ($attribute, $value, $fail) {
                        $validIncludes = ['blane', 'user', 'customer', 'blaneImage'];
                        $includes = explode(',', $value);
                        foreach ($includes as $include) {
                            if (!in_array($include, $validIncludes)) {
                                $fail('The selected ' . $attribute . ' is invalid.');
                            }
                        }
                    },
                ],
                'paginationSize' => 'nullable|integer|min:1',
                'sort_by' => 'nullable|string|in:created_at,date,time,status,source',
                'sort_order' => 'nullable|string|in:asc,desc',
                'source' => 'nullable|string|in:web,mobile,agent',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        $blaneIds = Blane::where('commerce_name', $request->input('commerce_name'))
            ->pluck('id')
            ->toArray();

        if (empty($blaneIds)) {
            return response()->json([
                'success' => false,
                'message' => 'No Blanes found for the specified vendor.',
            ], 404);
        }

        $query = Reservation::query()
            ->whereIn('blane_id', $blaneIds)
            ->where('status', 'pending')
            ->where(function ($q) {
                $twoDaysAgo = Carbon::now()->subHours(48);
                $q->whereNotNull('end_date')
                    ->where('end_date', '<=', $twoDaysAgo)
                    ->orWhere(function ($q2) use ($twoDaysAgo) {
                        $q2->whereNull('end_date')
                            ->where('date', '<=', $twoDaysAgo);
                    });
            });

        if ($request->has('source')) {
            $query->where('source', $request->input('source'));
        }
        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $includes = array_map(function ($include) {
                return $include === 'blaneImage' ? 'blane.blaneImages' : $include;
            }, $includes);
            $query->with($includes);
        }

        if (!in_array('customer', $includes ?? [])) {
            $query->with('customer');
        }

        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $allowedSortBy = ['created_at', 'date', 'time', 'status', 'source'];
        if (in_array($sortBy, $allowedSortBy)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $paginationSize = $request->input('paginationSize', 10);
        $reservations = $query->paginate($paginationSize);

        return response()->json([
            'success' => true,
            'message' => 'Pending reservations retrieved successfully.',
            'data' => ReservationResource::collection($reservations),
        ], 200);
    }

    /**
     * Get reservation ID by reservation number
     *
     * @OA\Get(
     *     path="/back/v1/reservations/by-number/{num_res}",
     *     tags={"Back - Reservations"},
     *     summary="Get reservation ID by number",
     *     description="Retrieve reservation ID using the reservation number (NUM_RES)",
     *     operationId="backGetReservationIdByNumber",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="num_res", in="path", required=true, description="Reservation number (e.g., RES-AB123456)", @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="Reservation found",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="NUM_RES", type="string", example="RES-AB123456")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")),
     *     @OA\Response(response=404, description="Reservation not found", @OA\JsonContent(ref="#/components/schemas/NotFoundResponse"))
     * )
     */
    public function getIdByNumber(string $num_res): JsonResponse
    {
        $reservation = Reservation::where('NUM_RES', $num_res)->first();

        if (!$reservation) {
            return response()->json(['message' => 'Reservation not found'], 404);
        }

        return response()->json([
            'id' => $reservation->id,
            'NUM_RES' => $reservation->NUM_RES,
        ], 200);
    }

}

