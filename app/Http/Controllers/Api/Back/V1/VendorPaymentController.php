<?php

namespace App\Http\Controllers\Api\Back\V1;

use App\Http\Controllers\Api\BaseController;
use App\Services\VendorPaymentService;
use App\Models\VendorPayment;
use App\Models\VendorPaymentLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Rap2hpoutre\FastExcel\FastExcel;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * @OA\Tag(name="Back - Vendor Payments", description="Vendor payment management and reporting")
 *
 * @OA\Schema(
 *     schema="VendorPayment",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="vendor_id", type="integer"),
 *     @OA\Property(property="order_id", type="integer", nullable=true),
 *     @OA\Property(property="reservation_id", type="integer", nullable=true),
 *     @OA\Property(property="total_amount_ttc", type="number", format="float"),
 *     @OA\Property(property="payment_type", type="string", enum={"full","partial"}),
 *     @OA\Property(property="commission_rate_applied", type="number", format="float"),
 *     @OA\Property(property="commission_amount_incl_vat", type="number", format="float"),
 *     @OA\Property(property="net_amount_ttc", type="number", format="float"),
 *     @OA\Property(property="transfer_status", type="string", enum={"pending","processed","complete"}),
 *     @OA\Property(property="transfer_date", type="string", format="date"),
 *     @OA\Property(property="debit_account", type="string"),
 *     @OA\Property(property="credit_account", type="string"),
 *     @OA\Property(property="booking_date", type="string", format="date"),
 *     @OA\Property(property="payment_date", type="string", format="date"),
 *     @OA\Property(property="week_start", type="string", format="date"),
 *     @OA\Property(property="week_end", type="string", format="date")
 * )
 */
class VendorPaymentController extends BaseController
{
    protected $paymentService;

    public function __construct(VendorPaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * List vendor payments with filters.
     *
     * @OA\Get(
     *     path="/back/v1/vendor-payments",
     *     tags={"Back - Vendor Payments"},
     *     summary="List vendor payments with filters",
     *     operationId="backVendorPaymentIndex",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="vendor_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="transfer_status", in="query", @OA\Schema(type="string", enum={"pending","processed","complete"})),
     *     @OA\Parameter(name="payment_type", in="query", @OA\Schema(type="string", enum={"full","partial"})),
     *     @OA\Parameter(name="category_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="start_date", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="end_date", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="week_start", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="week_end", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="paginationSize", in="query", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Payments retrieved", @OA\JsonContent(
     *         @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/VendorPayment")),
     *         @OA\Property(property="meta", type="object")
     *     )),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function index(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'vendor_id' => 'nullable|integer|exists:users,id',
                'transfer_status' => 'nullable|in:pending,processed,complete',
                'payment_type' => 'nullable|in:full,partial',
                'category_id' => 'nullable|integer|exists:categories,id',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date',
                'week_start' => 'nullable|date',
                'week_end' => 'nullable|date',
                'search' => 'nullable|string|max:255',
                'sort_by' => 'nullable|string',
                'sort_order' => 'nullable|in:asc,desc',
                'paginationSize' => 'nullable|integer|min:1|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'code' => 422,
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $filters = $request->only([
                'vendor_id',
                'transfer_status',
                'payment_type',
                'category_id',
                'start_date',
                'end_date',
                'week_start',
                'week_end',
                'search',
                'sort_by',
                'sort_order',
            ]);
            $filters['paginationSize'] = $request->input('paginationSize', 10);

            $payments = $this->paymentService->getPaymentsWithFilters($filters);

            return response()->json([
                'status' => true,
                'code' => 200,
                'message' => 'Vendor payments retrieved successfully',
                'data' => $payments->items(),
                'meta' => [
                    'total' => $payments->total(),
                    'current_page' => $payments->currentPage(),
                    'last_page' => $payments->lastPage(),
                    'per_page' => $payments->perPage(),
                    'from' => $payments->firstItem(),
                    'to' => $payments->lastItem(),
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'code' => 500,
                'message' => 'Failed to retrieve vendor payments',
                'errors' => [$this->safeExceptionMessage($e)],
            ], 500);
        }
    }

    /**
     * Get single payment.
     *
     * @OA\Get(
     *     path="/back/v1/vendor-payments/{id}",
     *     tags={"Back - Vendor Payments"},
     *     summary="Get single vendor payment",
     *     operationId="backVendorPaymentShow",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Payment retrieved"),
     *     @OA\Response(response=404, description="Payment not found")
     * )
     */
    public function show($id)
    {
        try {
            $payment = VendorPayment::with(['vendor', 'order', 'reservation', 'updatedBy', 'logs.admin'])
                ->findOrFail($id);

            return response()->json([
                'status' => true,
                'code' => 200,
                'message' => 'Vendor payment retrieved successfully',
                'data' => $payment,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'code' => 404,
                'message' => 'Vendor payment not found',
                'errors' => [$this->safeExceptionMessage($e)],
            ], 404);
        }
    }

    /**
     * Mark payments as processed (bulk).
     *
     * @OA\Post(
     *     path="/back/v1/vendor-payments/mark-processed",
     *     tags={"Back - Vendor Payments"},
     *     summary="Mark multiple payments as processed",
     *     operationId="backVendorPaymentMarkProcessed",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"payment_ids"},
     *         @OA\Property(property="payment_ids", type="array", @OA\Items(type="integer")),
     *         @OA\Property(property="transfer_date", type="string", format="date"),
     *         @OA\Property(property="note", type="string", maxLength=1000)
     *     )),
     *     @OA\Response(response=200, description="Payments marked as processed"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function markAsProcessed(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'payment_ids' => 'required|array|min:1',
                'payment_ids.*' => 'integer|exists:vendor_payments,id',
                'transfer_date' => 'nullable|date',
                'note' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'code' => 422,
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $adminId = auth()->id();
            $affected = $this->paymentService->markAsProcessed(
                $request->payment_ids,
                $adminId,
                $request->transfer_date,
                $request->note
            );

            return response()->json([
                'status' => true,
                'code' => 200,
                'message' => "{$affected} payment(s) marked as processed successfully",
                'data' => [
                    'affected_count' => $affected,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'code' => 500,
                'message' => 'Failed to mark payments as processed',
                'errors' => [$this->safeExceptionMessage($e)],
            ], 500);
        }
    }

    /**
     * Revert payment to pending.
     *
     * @OA\Post(
     *     path="/back/v1/vendor-payments/{id}/revert-pending",
     *     tags={"Back - Vendor Payments"},
     *     summary="Revert payment to pending status",
     *     operationId="backVendorPaymentRevertPending",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"note"},
     *         @OA\Property(property="note", type="string", maxLength=1000)
     *     )),
     *     @OA\Response(response=200, description="Payment reverted to pending"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function revertToPending(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'note' => 'required|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'code' => 422,
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $adminId = auth()->id();
            $payment = $this->paymentService->revertToPending($id, $adminId, $request->note);

            return response()->json([
                'status' => true,
                'code' => 200,
                'message' => 'Payment reverted to pending successfully',
                'data' => $payment,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'code' => 500,
                'message' => 'Failed to revert payment',
                'errors' => [$this->safeExceptionMessage($e)],
            ], 500);
        }
    }

    /**
     * Update payment (for date corrections).
     *
     * @OA\Put(
     *     path="/back/v1/vendor-payments/{id}",
     *     tags={"Back - Vendor Payments"},
     *     summary="Update payment dates",
     *     operationId="backVendorPaymentUpdate",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         @OA\Property(property="booking_date", type="string", format="date"),
     *         @OA\Property(property="payment_date", type="string", format="date"),
     *         @OA\Property(property="transfer_date", type="string", format="date")
     *     )),
     *     @OA\Response(response=200, description="Payment updated"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'booking_date' => 'nullable|date',
                'payment_date' => 'nullable|date',
                'transfer_date' => 'nullable|date',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'code' => 422,
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $payment = VendorPayment::findOrFail($id);
            $adminId = auth()->id();

            $previousData = $payment->only(['booking_date', 'payment_date', 'transfer_date']);

            $payment->update($request->only(['booking_date', 'payment_date', 'transfer_date']));

            VendorPaymentLog::create([
                'vendor_payment_id' => $payment->id,
                'admin_id' => $adminId,
                'action' => 'date_updated',
                'previous_status' => json_encode($previousData),
                'new_status' => json_encode($payment->only(['booking_date', 'payment_date', 'transfer_date'])),
                'admin_note' => 'Date correction',
                'created_at' => now(),
            ]);

            return response()->json([
                'status' => true,
                'code' => 200,
                'message' => 'Payment updated successfully',
                'data' => $payment->fresh(),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'code' => 500,
                'message' => 'Failed to update payment',
                'errors' => [$this->safeExceptionMessage($e)],
            ], 500);
        }
    }

    /**
     * Export payments to Excel.
     *
     * @OA\Get(
     *     path="/back/v1/vendor-payments/export/excel",
     *     tags={"Back - Vendor Payments"},
     *     summary="Export payments to Excel",
     *     operationId="backVendorPaymentExportExcel",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="vendor_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="transfer_status", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="payment_type", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="start_date", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="end_date", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Response(response=200, description="Excel file download"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function exportExcel(Request $request)
    {
        try {
            $filters = $request->only([
                'vendor_id',
                'transfer_status',
                'payment_type',
                'start_date',
                'end_date',
                'week_start',
                'week_end',
            ]);

            $payments = VendorPayment::with(['vendor', 'order', 'reservation'])
                ->when(isset($filters['vendor_id']), function ($q) use ($filters) {
                    $q->where('vendor_id', $filters['vendor_id']);
                })
                ->when(isset($filters['transfer_status']), function ($q) use ($filters) {
                    $q->where('transfer_status', $filters['transfer_status']);
                })
                ->when(isset($filters['payment_type']), function ($q) use ($filters) {
                    $q->where('payment_type', $filters['payment_type']);
                })
                ->when(isset($filters['start_date']), function ($q) use ($filters) {
                    $q->where('payment_date', '>=', $filters['start_date']);
                })
                ->when(isset($filters['end_date']), function ($q) use ($filters) {
                    $q->where('payment_date', '<=', $filters['end_date']);
                })
                ->when(isset($filters['week_start']) && isset($filters['week_end']), function ($q) use ($filters) {
                    $q->whereBetween('week_start', [$filters['week_start'], $filters['week_end']]);
                })
                ->get();

            $data = $payments->map(function ($payment) {
                return [
                    'Vendor Name' => $payment->vendor->name ?? '',
                    'Company Name' => $payment->vendor->company_name ?? '',
                    'Category' => $payment->vendor->businessCategory ?? '',
                    'Total Amount Paid (TTC)' => $payment->total_amount_ttc,
                    'Payment Type' => ucfirst($payment->payment_type),
                    'Applied Commission Rate' => $payment->commission_rate_applied . '%',
                    'Commission Amount (TTC)' => $payment->commission_amount_incl_vat,
                    'Net Amount to Reimburse (TTC)' => $payment->net_amount_ttc,
                    'Bank Account (RIB)' => $payment->credit_account ?? '',
                    'Transfer Status' => ucfirst($payment->transfer_status),
                    'Transfer Date' => $payment->transfer_date ? $payment->transfer_date->format('Y-m-d') : '',
                    'Reason' => $payment->reason,
                    'Debit Account' => $payment->debit_account,
                    'Booking Date' => $payment->booking_date ? Carbon::parse($payment->booking_date)->format('Y-m-d') : '',
                    'Payment Date' => Carbon::parse($payment->payment_date)->format('Y-m-d'),
                ];
            });

            return (new FastExcel($data))->download('vendor_payments_' . now()->format('Y-m-d') . '.xlsx');
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'code' => 500,
                'message' => 'Failed to export Excel',
                'errors' => [$this->safeExceptionMessage($e)],
            ], 500);
        }
    }

    /**
     * Export payments to PDF.
     *
     * @OA\Get(
     *     path="/back/v1/vendor-payments/export/pdf",
     *     tags={"Back - Vendor Payments"},
     *     summary="Export payments to PDF",
     *     operationId="backVendorPaymentExportPdf",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="vendor_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="transfer_status", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="payment_type", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="start_date", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="end_date", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Response(response=200, description="PDF file download"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function exportPDF(Request $request)
    {
        try {
            $filters = $request->only([
                'vendor_id',
                'transfer_status',
                'payment_type',
                'start_date',
                'end_date',
                'week_start',
                'week_end',
            ]);

            $payments = VendorPayment::with(['vendor', 'order', 'reservation'])
                ->when(isset($filters['vendor_id']), function ($q) use ($filters) {
                    $q->where('vendor_id', $filters['vendor_id']);
                })
                ->when(isset($filters['transfer_status']), function ($q) use ($filters) {
                    $q->where('transfer_status', $filters['transfer_status']);
                })
                ->when(isset($filters['payment_type']), function ($q) use ($filters) {
                    $q->where('payment_type', $filters['payment_type']);
                })
                ->when(isset($filters['start_date']), function ($q) use ($filters) {
                    $q->where('payment_date', '>=', $filters['start_date']);
                })
                ->when(isset($filters['end_date']), function ($q) use ($filters) {
                    $q->where('payment_date', '<=', $filters['end_date']);
                })
                ->when(isset($filters['week_start']) && isset($filters['week_end']), function ($q) use ($filters) {
                    $q->whereBetween('week_start', [$filters['week_start'], $filters['week_end']]);
                })
                ->get();

            $pdf = Pdf::loadView('vendor_payments.report', [
                'payments' => $payments,
                'filters' => $filters,
                'generated_at' => now(),
            ]);

            return $pdf->download('vendor_payments_' . now()->format('Y-m-d') . '.pdf');
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'code' => 500,
                'message' => 'Failed to export PDF',
                'errors' => [$this->safeExceptionMessage($e)],
            ], 500);
        }
    }

    /**
     * Generate banking report.
     *
     * @OA\Get(
     *     path="/back/v1/vendor-payments/banking-report",
     *     tags={"Back - Vendor Payments"},
     *     summary="Generate banking report for week",
     *     operationId="backVendorPaymentBankingReport",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="week_start", in="query", required=true, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="week_end", in="query", required=true, @OA\Schema(type="string", format="date")),
     *     @OA\Response(response=200, description="Banking report generated"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function bankingReport(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'week_start' => 'required|date',
                'week_end' => 'required|date|after_or_equal:week_start',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'code' => 422,
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $weekStart = Carbon::parse($request->week_start);
            $weekEnd = Carbon::parse($request->week_end);

            $report = $this->paymentService->generateBankingReport($weekStart, $weekEnd);

            return response()->json([
                'status' => true,
                'code' => 200,
                'message' => 'Banking report generated successfully',
                'data' => $report,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'code' => 500,
                'message' => 'Failed to generate banking report',
                'errors' => [$this->safeExceptionMessage($e)],
            ], 500);
        }
    }

    /**
     * View audit logs.
     *
     * @OA\Get(
     *     path="/back/v1/vendor-payments/logs",
     *     tags={"Back - Vendor Payments"},
     *     summary="View payment audit logs",
     *     operationId="backVendorPaymentLogs",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="vendor_payment_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="admin_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="paginationSize", in="query", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Audit logs retrieved"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function logs(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'vendor_payment_id' => 'nullable|integer|exists:vendor_payments,id',
                'admin_id' => 'nullable|integer|exists:users,id',
                'paginationSize' => 'nullable|integer|min:1|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'code' => 422,
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $query = VendorPaymentLog::with(['vendorPayment.vendor', 'admin']);

            if ($request->has('vendor_payment_id')) {
                $query->where('vendor_payment_id', $request->vendor_payment_id);
            }

            if ($request->has('admin_id')) {
                $query->where('admin_id', $request->admin_id);
            }

            $logs = $query->orderBy('created_at', 'desc')
                ->paginate($request->input('paginationSize', 10));

            return response()->json([
                'status' => true,
                'code' => 200,
                'message' => 'Audit logs retrieved successfully',
                'data' => $logs->items(),
                'meta' => [
                    'total' => $logs->total(),
                    'current_page' => $logs->currentPage(),
                    'last_page' => $logs->lastPage(),
                    'per_page' => $logs->perPage(),
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'code' => 500,
                'message' => 'Failed to retrieve audit logs',
                'errors' => [$this->safeExceptionMessage($e)],
            ], 500);
        }
    }

    /**
     * Dashboard stats.
     *
     * @OA\Get(
     *     path="/back/v1/vendor-payments/dashboard",
     *     tags={"Back - Vendor Payments"},
     *     summary="Get payment dashboard statistics",
     *     operationId="backVendorPaymentDashboard",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Dashboard stats retrieved", @OA\JsonContent(
     *         @OA\Property(property="data", type="object",
     *             @OA\Property(property="pending_count", type="integer"),
     *             @OA\Property(property="processed_count", type="integer"),
     *             @OA\Property(property="complete_count", type="integer"),
     *             @OA\Property(property="total_pending_amount", type="number"),
     *             @OA\Property(property="total_processed_amount", type="number"),
     *             @OA\Property(property="total_complete_amount", type="number"),
     *             @OA\Property(property="current_week", type="object")
     *         )
     *     )),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function dashboard(Request $request)
    {
        try {
            $pendingPayments = VendorPayment::pending()->count();
            $processedPayments = VendorPayment::processed()->count();
            $completePayments = VendorPayment::complete()->count();
            $totalPendingAmount = VendorPayment::pending()->sum('net_amount_ttc');
            $totalProcessedAmount = VendorPayment::processed()->sum('net_amount_ttc');
            $totalCompleteAmount = VendorPayment::complete()->sum('net_amount_ttc');

            // Current week stats
            $weekRange = VendorPayment::getWeekRange();
            $currentWeekPayments = VendorPayment::byWeek($weekRange['week_start'], $weekRange['week_end'])->get();
            $currentWeekPending = $currentWeekPayments->where('transfer_status', 'pending')->sum('net_amount_ttc');
            $currentWeekProcessed = $currentWeekPayments->where('transfer_status', 'processed')->sum('net_amount_ttc');
            $currentWeekComplete = $currentWeekPayments->where('transfer_status', 'complete')->sum('net_amount_ttc');

            return response()->json([
                'status' => true,
                'code' => 200,
                'message' => 'Dashboard stats retrieved successfully',
                'data' => [
                    'pending_count' => $pendingPayments,
                    'processed_count' => $processedPayments,
                    'complete_count' => $completePayments,
                    'total_pending_amount' => round($totalPendingAmount, 2),
                    'total_processed_amount' => round($totalProcessedAmount, 2),
                    'total_complete_amount' => round($totalCompleteAmount, 2),
                    'current_week' => [
                        'week_start' => $weekRange['week_start']->format('Y-m-d'),
                        'week_end' => $weekRange['week_end']->format('Y-m-d'),
                        'pending_amount' => round($currentWeekPending, 2),
                        'processed_amount' => round($currentWeekProcessed, 2),
                        'complete_amount' => round($currentWeekComplete, 2),
                        'payment_count' => $currentWeekPayments->count(),
                    ],
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'code' => 500,
                'message' => 'Failed to retrieve dashboard stats',
                'errors' => [$this->safeExceptionMessage($e)],
            ], 500);
        }
    }

    /**
     * Weekly summary.
     *
     * @OA\Get(
     *     path="/back/v1/vendor-payments/weekly-summary",
     *     tags={"Back - Vendor Payments"},
     *     summary="Get weekly payment summary",
     *     operationId="backVendorPaymentWeeklySummary",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="week_start", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Response(response=200, description="Weekly summary retrieved"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function weeklySummary(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'week_start' => 'nullable|date',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'code' => 422,
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $weekStart = $request->has('week_start')
                ? Carbon::parse($request->week_start)
                : Carbon::now()->startOfWeek(Carbon::MONDAY);

            $weekRange = VendorPayment::getWeekRange($weekStart);
            $report = $this->paymentService->generateBankingReport($weekRange['week_start'], $weekRange['week_end']);

            return response()->json([
                'status' => true,
                'code' => 200,
                'message' => 'Weekly summary retrieved successfully',
                'data' => $report,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'code' => 500,
                'message' => 'Failed to retrieve weekly summary',
                'errors' => [$this->safeExceptionMessage($e)],
            ], 500);
        }
    }

    /**
     * Update payment status and save data.
     *
     * @OA\Patch(
     *     path="/back/v1/vendor-payments/{id}/status",
     *     tags={"Back - Vendor Payments"},
     *     summary="Update payment status and details",
     *     operationId="backVendorPaymentUpdateStatus",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         @OA\Property(property="transfer_status", type="string", enum={"pending","processed","complete"}),
     *         @OA\Property(property="transfer_date", type="string", format="date"),
     *         @OA\Property(property="debit_account", type="string"),
     *         @OA\Property(property="credit_account", type="string"),
     *         @OA\Property(property="reason", type="string"),
     *         @OA\Property(property="booking_date", type="string", format="date"),
     *         @OA\Property(property="payment_date", type="string", format="date"),
     *         @OA\Property(property="note", type="string")
     *     )),
     *     @OA\Response(response=200, description="Payment status updated"),
     *     @OA\Response(response=404, description="Payment not found"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function updateStatus(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'transfer_status' => 'nullable|in:pending,processed,complete',
                'transfer_date' => 'nullable|date',
                'debit_account' => 'nullable|string|max:255',
                'credit_account' => 'nullable|string|max:255',
                'reason' => 'nullable|string|max:1000',
                'booking_date' => 'nullable|date',
                'payment_date' => 'nullable|date',
                'note' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'code' => 422,
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Check if at least one field is provided
            $allowedFields = [
                'transfer_status',
                'transfer_date',
                'debit_account',
                'credit_account',
                'reason',
                'booking_date',
                'payment_date',
                'note'
            ];

            $hasData = false;
            foreach ($allowedFields as $field) {
                if ($request->has($field)) {
                    $hasData = true;
                    break;
                }
            }

            if (!$hasData) {
                return response()->json([
                    'status' => false,
                    'code' => 422,
                    'message' => 'At least one field must be provided for update',
                    'errors' => ['No update data provided'],
                ], 422);
            }

            $adminId = auth()->id();
            $payment = $this->paymentService->updatePaymentStatus(
                $id,
                $request->only($allowedFields),
                $adminId
            );

            return response()->json([
                'status' => true,
                'code' => 200,
                'message' => 'Payment status updated successfully',
                'data' => $payment->load(['vendor', 'order', 'reservation', 'updatedBy', 'logs.admin']),
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'code' => 404,
                'message' => 'Vendor payment not found',
                'errors' => ['Payment ID does not exist'],
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'code' => 500,
                'message' => 'Failed to update payment status',
                'errors' => [$this->safeExceptionMessage($e)],
            ], 500);
        }
    }
}

