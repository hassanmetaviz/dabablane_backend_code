<?php

namespace App\Http\Controllers\Api\Front\V1;

use App\Http\Controllers\Controller;
use App\Services\VendorRevenueService;
use App\Models\VendorMonthlyInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Rap2hpoutre\FastExcel\FastExcel;
use Barryvdh\DomPDF\Facade\Pdf;

class VendorRevenueController extends Controller
{
    protected $revenueService;

    public function __construct(VendorRevenueService $revenueService)
    {
        $this->revenueService = $revenueService;
    }

    /**
     * Get vendor overview (Tab 1).
     */
    public function overview(Request $request)
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

            $vendorId = auth()->id();
            $weekStart = $request->has('week_start')
                ? Carbon::parse($request->week_start)
                : null;

            $overview = $this->revenueService->getVendorOverview($vendorId, $weekStart);

            return response()->json([
                'status' => true,
                'code' => 200,
                'message' => 'Vendor overview retrieved successfully',
                'data' => $overview,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'code' => 500,
                'message' => 'Failed to retrieve vendor overview',
                'errors' => [$e->getMessage()],
            ], 500);
        }
    }

    /**
     * Get vendor transactions (Tab 2).
     */
    public function transactions(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'months' => 'nullable|integer|min:1|max:24',
                'payment_type' => 'nullable|in:full,partial',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'code' => 422,
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $vendorId = auth()->id();
            $months = $request->input('months', 6);

            $transactions = $this->revenueService->getVendorTransactions($vendorId, $months);

            if ($request->has('payment_type')) {
                $transactions = $transactions->where('payment_type', $request->payment_type);
            }

            return response()->json([
                'status' => true,
                'code' => 200,
                'message' => 'Vendor transactions retrieved successfully',
                'data' => $transactions->values(),
                'meta' => [
                    'total' => $transactions->count(),
                    'months' => $months,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'code' => 500,
                'message' => 'Failed to retrieve vendor transactions',
                'errors' => [$e->getMessage()],
            ], 500);
        }
    }

    /**
     * Download monthly invoice.
     */
    public function downloadInvoice(Request $request, $month, $year)
    {
        try {
            $validator = Validator::make(['month' => $month, 'year' => $year], [
                'month' => 'required|integer|min:1|max:12',
                'year' => 'required|integer|min:2020|max:2100',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'code' => 422,
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $vendorId = auth()->id();

            $invoice = $this->revenueService->generateMonthlyInvoice($vendorId, $month, $year);

            if (!$invoice->pdf_path) {
                $pdf = Pdf::loadView('vendor_invoices.monthly', [
                    'invoice' => $invoice->load('vendor'),
                    'payments' => $this->revenueService->getVendorTransactions($vendorId, 1)
                        ->filter(function ($payment) use ($month, $year) {
                            $startOfMonth = Carbon::create($year, $month, 1)->startOfMonth();
                            $endOfMonth = Carbon::create($year, $month, 1)->endOfMonth();
                            return $payment->payment_date >= $startOfMonth
                                && $payment->payment_date <= $endOfMonth;
                        }),
                ]);

                $pdfFileName = 'invoice_' . $invoice->invoice_number . '.pdf';
                $pdfPath = 'vendor_invoices/' . $pdfFileName;
                $fullPath = storage_path('app/public/uploads/' . $pdfPath);

                $directory = dirname($fullPath);
                if (!file_exists($directory)) {
                    mkdir($directory, 0755, true);
                }

                $pdf->save($fullPath);
                $invoice->update(['pdf_path' => 'uploads/' . $pdfPath]);
            }

            $filePath = storage_path('app/public/' . $invoice->pdf_path);
            if (file_exists($filePath)) {
                return response()->download($filePath, 'invoice_' . $invoice->invoice_number . '.pdf');
            }

            return response()->json([
                'status' => false,
                'code' => 404,
                'message' => 'Invoice PDF not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'code' => 500,
                'message' => 'Failed to download invoice',
                'errors' => [$e->getMessage()],
            ], 500);
        }
    }

    /**
     * Export transactions to Excel.
     */
    public function exportExcel(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'months' => 'nullable|integer|min:1|max:24',
                'payment_type' => 'nullable|in:full,partial',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'code' => 422,
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $vendorId = auth()->id();
            $months = $request->input('months', 6);

            $transactions = $this->revenueService->getVendorTransactions($vendorId, $months);

            if ($request->has('payment_type')) {
                $transactions = $transactions->where('payment_type', $request->payment_type);
            }

            $data = $transactions->map(function ($payment) {
                return [
                    'Date' => $payment->payment_date->format('Y-m-d'),
                    'Payment Type' => ucfirst($payment->payment_type),
                    'Total Amount (TTC)' => $payment->total_amount_ttc,
                    'Commission Rate' => $payment->commission_rate_applied . '%',
                    'Commission Amount (TTC)' => $payment->commission_amount_incl_vat,
                    'Net Amount (TTC)' => $payment->net_amount_ttc,
                    'Transfer Status' => ucfirst($payment->transfer_status),
                    'Transfer Date' => $payment->transfer_date ? $payment->transfer_date->format('Y-m-d') : '',
                    'Order/Reservation ID' => $payment->order_id ?? $payment->reservation_id,
                ];
            });

            return (new FastExcel($data))->download('vendor_transactions_' . now()->format('Y-m-d') . '.xlsx');
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'code' => 500,
                'message' => 'Failed to export Excel',
                'errors' => [$e->getMessage()],
            ], 500);
        }
    }

    /**
     * Export transactions to PDF.
     */
    public function exportPDF(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'months' => 'nullable|integer|min:1|max:24',
                'payment_type' => 'nullable|in:full,partial',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'code' => 422,
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $vendorId = auth()->id();
            $months = $request->input('months', 6);

            $transactions = $this->revenueService->getVendorTransactions($vendorId, $months);

            if ($request->has('payment_type')) {
                $transactions = $transactions->where('payment_type', $request->payment_type);
            }

            $pdf = Pdf::loadView('vendor_revenues.transactions', [
                'transactions' => $transactions,
                'vendor' => auth()->user(),
                'generated_at' => now(),
            ]);

            return $pdf->download('vendor_transactions_' . now()->format('Y-m-d') . '.pdf');
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'code' => 500,
                'message' => 'Failed to export PDF',
                'errors' => [$e->getMessage()],
            ], 500);
        }
    }

    /**
     * Get vendor statistics.
     */
    public function statistics(Request $request)
    {
        try {
            $vendorId = auth()->id();
            $statistics = $this->revenueService->getVendorStatistics($vendorId);

            return response()->json([
                'status' => true,
                'code' => 200,
                'message' => 'Vendor statistics retrieved successfully',
                'data' => $statistics,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'code' => 500,
                'message' => 'Failed to retrieve vendor statistics',
                'errors' => [$e->getMessage()],
            ], 500);
        }
    }

    /**
     * Create monthly invoice for vendor commission.
     * Shows how much vendor earned and how much commission was deducted for admin.
     */
    public function createInvoice(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'month' => 'required|integer|min:1|max:12',
                'year' => 'required|integer|min:2020|max:2100',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'code' => 422,
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $vendorId = auth()->id();
            $month = $request->input('month');
            $year = $request->input('year');

            $invoiceData = $this->revenueService->createMonthlyInvoice($vendorId, $month, $year);

            return response()->json([
                'status' => true,
                'code' => 200,
                'message' => 'Monthly invoice created successfully',
                'data' => $invoiceData,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'code' => 500,
                'message' => 'Failed to create monthly invoice',
                'errors' => [$e->getMessage()],
            ], 500);
        }
    }
}

