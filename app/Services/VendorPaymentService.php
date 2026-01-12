<?php

namespace App\Services;

use App\Models\VendorPayment;
use App\Models\VendorPaymentLog;
use App\Models\Order;
use App\Models\Reservation;
use App\Services\CommissionCalculationService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VendorPaymentService
{
    protected $commissionService;

    public function __construct(CommissionCalculationService $commissionService)
    {
        $this->commissionService = $commissionService;
    }

    /**
     * Create vendor payment from order.
     *
     * @param Order $order
     * @return VendorPayment
     */
    public function createPaymentFromOrder(Order $order): VendorPayment
    {

        $paymentType = $order->payment_method === 'partiel' ? 'partial' : 'full';
        $totalAmount = $paymentType === 'partial'
            ? ($order->partiel_price ?? 0)
            : ($order->total_price ?? 0);

        if ($totalAmount <= 0 || $order->payment_method === 'cash') {
            throw new \Exception('Cannot create payment for offline or zero-amount order');
        }

        $blane = $order->blane;
        $vendor = $blane->vendor()->first();

        if (!$vendor) {
            throw new \Exception('Vendor not found for this blane');
        }

        $paymentData = [
            'vendor_id' => $vendor->id,
            'order_id' => $order->id,
            'total_amount_ttc' => $totalAmount,
            'payment_type' => $paymentType,
            'category_id' => $blane->categories_id,
            'booking_date' => $order->created_at->toDateString(),
            'payment_date' => $order->updated_at->toDateString(),
        ];

        return $this->commissionService->calculateVendorPayment($paymentData);
    }

    /**
     * Create vendor payment from reservation.
     *
     * @param Reservation $reservation
     * @return VendorPayment
     */
    public function createPaymentFromReservation(Reservation $reservation): VendorPayment
    {

        $paymentType = $reservation->payment_method === 'partiel' ? 'partial' : 'full';
        $totalAmount = $paymentType === 'partial'
            ? ($reservation->partiel_price ?? 0)
            : ($reservation->total_price ?? 0);

        if ($totalAmount <= 0 || $reservation->payment_method === 'cash') {
            throw new \Exception('Cannot create payment for offline or zero-amount reservation');
        }

        $blane = $reservation->blane;
        $vendor = $blane->vendor()->first();

        if (!$vendor) {
            throw new \Exception('Vendor not found for this blane');
        }

        $paymentData = [
            'vendor_id' => $vendor->id,
            'reservation_id' => $reservation->id,
            'total_amount_ttc' => $totalAmount,
            'payment_type' => $paymentType,
            'category_id' => $blane->categories_id,
            'booking_date' => $reservation->date ?? $reservation->created_at->toDateString(),
            'payment_date' => $reservation->updated_at->toDateString(),
        ];

        return $this->commissionService->calculateVendorPayment($paymentData);
    }

    /**
     * Mark payments as processed (bulk).
     *
     * @param array $paymentIds
     * @param int $adminId
     * @param string|null $transferDate
     * @param string|null $note
     * @return int Number of affected rows
     */
    public function markAsProcessed(array $paymentIds, int $adminId, ?string $transferDate = null, ?string $note = null): int
    {
        $transferDate = $transferDate ? Carbon::parse($transferDate) : Carbon::now();

        $affected = DB::transaction(function () use ($paymentIds, $adminId, $transferDate, $note) {
            $count = 0;
            foreach ($paymentIds as $paymentId) {
                $payment = VendorPayment::find($paymentId);
                if ($payment && $payment->transfer_status === 'pending') {
                    $payment->markAsProcessed($adminId, $transferDate, $note);
                    $count++;
                }
            }

            if ($count > 0) {
                VendorPaymentLog::create([
                    'vendor_payment_id' => $paymentIds[0],
                    'admin_id' => $adminId,
                    'action' => 'marked_processed',
                    'previous_status' => 'pending',
                    'new_status' => 'processed',
                    'affected_rows' => $count,
                    'admin_note' => $note ?? "Bulk update: {$count} payments marked as processed",
                    'created_at' => now(),
                ]);
            }

            return $count;
        });

        return $affected;
    }

    /**
     * Revert payment to pending.
     *
     * @param int $paymentId
     * @param int $adminId
     * @param string $note
     * @return VendorPayment
     */
    public function revertToPending(int $paymentId, int $adminId, string $note): VendorPayment
    {
        $payment = VendorPayment::findOrFail($paymentId);
        $payment->revertToPending($adminId, $note);
        return $payment->fresh();
    }

    /**
     * Get weekly payments.
     *
     * @param Carbon $weekStart
     * @param Carbon $weekEnd
     * @return Collection
     */
    public function getWeeklyPayments(Carbon $weekStart, Carbon $weekEnd): Collection
    {
        return VendorPayment::byWeek($weekStart, $weekEnd)
            ->with(['vendor', 'order', 'reservation'])
            ->orderBy('vendor_id')
            ->orderBy('payment_date')
            ->get();
    }

    /**
     * Generate banking report data.
     *
     * @param Carbon $weekStart
     * @param Carbon $weekEnd
     * @return array
     */
    public function generateBankingReport(Carbon $weekStart, Carbon $weekEnd): array
    {
        $payments = $this->getWeeklyPayments($weekStart, $weekEnd);

        $totalAmount = $payments->sum('net_amount_ttc');
        $totalPending = $payments->where('transfer_status', 'pending')->sum('net_amount_ttc');
        $totalProcessed = $payments->where('transfer_status', 'processed')->sum('net_amount_ttc');
        $totalComplete = $payments->where('transfer_status', 'complete')->sum('net_amount_ttc');

        return [
            'week_start' => $weekStart->format('Y-m-d'),
            'week_end' => $weekEnd->format('Y-m-d'),
            'generated_at' => now()->format('Y-m-d H:i:s'),
            'payments' => $payments,
            'summary' => [
                'total_payments' => $payments->count(),
                'total_amount_ttc' => $totalAmount,
                'pending_amount' => $totalPending,
                'processed_amount' => $totalProcessed,
                'complete_amount' => $totalComplete,
            ],
        ];
    }

    /**
     * Update payment status and save additional data.
     *
     * @param int $paymentId
     * @param array $data
     * @param int $adminId
     * @return VendorPayment
     */
    public function updatePaymentStatus(int $paymentId, array $data, int $adminId): VendorPayment
    {
        $payment = VendorPayment::findOrFail($paymentId);

        $previousStatus = $payment->transfer_status;
        $previousData = $payment->only([
            'transfer_status',
            'transfer_date',
            'debit_account',
            'credit_account',
            'reason',
            'booking_date',
            'payment_date'
        ]);

        $updateData = [
            'updated_by' => $adminId,
        ];

        if (isset($data['transfer_status'])) {
            $updateData['transfer_status'] = $data['transfer_status'];

            if (in_array($data['transfer_status'], ['processed', 'complete']) && !isset($data['transfer_date'])) {
                $updateData['transfer_date'] = Carbon::now();
            }

            if ($data['transfer_status'] === 'pending') {
                $updateData['transfer_date'] = null;
            }
        }

        if (isset($data['transfer_date'])) {
            $updateData['transfer_date'] = Carbon::parse($data['transfer_date']);
        }

        if (isset($data['debit_account'])) {
            $updateData['debit_account'] = $data['debit_account'];
        }

        if (isset($data['credit_account'])) {
            $updateData['credit_account'] = $data['credit_account'];
        }

        if (isset($data['reason'])) {
            $updateData['reason'] = $data['reason'];
        }

        if (isset($data['booking_date'])) {
            $updateData['booking_date'] = Carbon::parse($data['booking_date']);
        }

        if (isset($data['payment_date'])) {
            $updateData['payment_date'] = Carbon::parse($data['payment_date']);
        }

        DB::transaction(function () use ($payment, $updateData, $adminId, $previousStatus, $previousData, $data) {
            $payment->update($updateData);

            $newStatus = $updateData['transfer_status'] ?? $previousStatus;
            $action = $newStatus !== $previousStatus
                ? ($newStatus === 'processed' ? 'status_changed_to_processed'
                    : ($newStatus === 'complete' ? 'status_changed_to_complete'
                        : 'status_changed_to_pending'))
                : 'payment_updated';

            VendorPaymentLog::create([
                'vendor_payment_id' => $payment->id,
                'admin_id' => $adminId,
                'action' => $action,
                'previous_status' => json_encode($previousData),
                'new_status' => json_encode($payment->only([
                    'transfer_status',
                    'transfer_date',
                    'debit_account',
                    'credit_account',
                    'reason',
                    'booking_date',
                    'payment_date'
                ])),
                'admin_note' => $data['note'] ?? ($newStatus !== $previousStatus
                    ? "Status changed from {$previousStatus} to {$newStatus}"
                    : 'Payment data updated'),
                'created_at' => now(),
            ]);
        });

        return $payment->fresh();
    }

    /**
     * Get payments with filters.
     *
     * @param array $filters
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getPaymentsWithFilters(array $filters)
    {
        $query = VendorPayment::with(['vendor', 'order', 'reservation', 'updatedBy']);

        if (isset($filters['vendor_id'])) {
            $query->byVendor($filters['vendor_id']);
        }

        if (isset($filters['transfer_status'])) {
            if ($filters['transfer_status'] === 'pending') {
                $query->pending();
            } elseif ($filters['transfer_status'] === 'processed') {
                $query->processed();
            } elseif ($filters['transfer_status'] === 'complete') {
                $query->complete();
            }
        }

        if (isset($filters['payment_type'])) {
            $query->byPaymentType($filters['payment_type']);
        }

        if (isset($filters['category_id'])) {
            $query->whereHas('vendor', function ($q) use ($filters) {
            });
        }

        if (isset($filters['start_date'])) {
            $query->where('payment_date', '>=', $filters['start_date']);
        }
        if (isset($filters['end_date'])) {
            $query->where('payment_date', '<=', $filters['end_date']);
        }

        if (isset($filters['week_start']) && isset($filters['week_end'])) {
            $query->byWeek(Carbon::parse($filters['week_start']), Carbon::parse($filters['week_end']));
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('vendor', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%");
            });
        }

        $sortBy = $filters['sort_by'] ?? 'payment_date';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $filters['paginationSize'] ?? 10;
        return $query->paginate($perPage);
    }
}

