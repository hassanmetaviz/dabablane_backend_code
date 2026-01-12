<?php

namespace App\Services;

use App\Models\VendorPayment;
use App\Models\VendorMonthlyInvoice;
use App\Models\CommissionSettings;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VendorRevenueService
{
    /**
     * Get vendor overview (Tab 1).
     *
     * @param int $vendorId
     * @param Carbon|null $weekStart
     * @return array
     */
    public function getVendorOverview(int $vendorId, ?Carbon $weekStart = null): array
    {
        $weekStart = $weekStart ?? Carbon::now()->startOfWeek(Carbon::MONDAY);
        $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);

        $lastWeekStart = $weekStart->copy()->subWeek()->startOfWeek(Carbon::MONDAY);
        $lastWeekEnd = $lastWeekStart->copy()->endOfWeek(Carbon::SUNDAY);

        $allPayments = VendorPayment::byVendor($vendorId)
            ->whereIn('transfer_status', ['pending', 'processed'])
            ->get();

        $currentWeekPayments = $allPayments->filter(function ($payment) use ($weekStart, $weekEnd) {
            return $payment->week_start >= $weekStart && $payment->week_end <= $weekEnd;
        });

        $lastWeekPayments = $allPayments->filter(function ($payment) use ($lastWeekStart, $lastWeekEnd) {
            return $payment->week_start >= $lastWeekStart && $payment->week_end <= $lastWeekEnd;
        });

        $totalRevenue = $allPayments->sum('total_amount_ttc');
        $expectedReimbursement = $currentWeekPayments->where('transfer_status', 'pending')->sum('net_amount_ttc');

        $fullPayments = $allPayments->where('payment_type', 'full');
        $fullPaymentsTotal = $fullPayments->sum('total_amount_ttc');
        $fullPaymentsReimbursement = $fullPayments->where('transfer_status', 'pending')->sum('net_amount_ttc');

        $partialPayments = $allPayments->where('payment_type', 'partial');
        $partialPaymentsTotal = $partialPayments->sum('total_amount_ttc');
        $partialPaymentsReimbursement = $partialPayments->where('transfer_status', 'pending')->sum('net_amount_ttc');


        $settings = CommissionSettings::getSettings();
        $vendor = User::find($vendorId);

        $commissionInfo = [
            'full_payment_rate' => 'x%',
            'partial_payment_rate' => $settings->partial_payment_commission_rate . '%',
            'vat_rate' => $settings->vat_rate . '%',
        ];

        $nextWednesday = Carbon::now()->next(Carbon::WEDNESDAY);
        if ($nextWednesday->isPast()) {
            $nextWednesday = Carbon::now()->addWeek()->next(Carbon::WEDNESDAY);
        }

        $lastWeekExpected = $lastWeekPayments->where('transfer_status', 'pending')->sum('net_amount_ttc');

        return [
            'total_online_revenue_ttc' => round($totalRevenue, 2),
            'expected_reimbursement_week' => round($expectedReimbursement, 2),
            'full_payments' => [
                'total_amount_ttc' => round($fullPaymentsTotal, 2),
                'expected_reimbursement' => round($fullPaymentsReimbursement, 2),
                'count' => $fullPayments->count(),
            ],
            'partial_payments' => [
                'total_amount_ttc' => round($partialPaymentsTotal, 2),
                'expected_reimbursement' => round($partialPaymentsReimbursement, 2),
                'count' => $partialPayments->count(),
            ],
            'commission_structure' => $commissionInfo,
            'scheduled_transfer_date' => $nextWednesday->format('Y-m-d'),
            'last_week_expected_amount' => round($lastWeekExpected, 2),
            'transfer_status_summary' => [
                'pending_count' => $currentWeekPayments->where('transfer_status', 'pending')->count(),
                'processed_count' => $currentWeekPayments->where('transfer_status', 'processed')->count(),
            ],
        ];
    }

    /**
     * Get vendor transactions (Tab 2).
     *
     * @param int $vendorId
     * @param int $months
     * @return Collection
     */
    public function getVendorTransactions(int $vendorId, int $months = 6): Collection
    {
        $startDate = Carbon::now()->subMonths($months)->startOfDay();

        return VendorPayment::byVendor($vendorId)
            ->where('payment_date', '>=', $startDate)
            ->with(['order', 'reservation'])
            ->orderBy('payment_date', 'desc')
            ->get();
    }

    /**
     * Get expected reimbursement for a period.
     *
     * @param int $vendorId
     * @param Carbon $weekStart
     * @param Carbon $weekEnd
     * @return array
     */
    public function getExpectedReimbursement(int $vendorId, Carbon $weekStart, Carbon $weekEnd): array
    {
        $payments = VendorPayment::byVendor($vendorId)
            ->byWeek($weekStart, $weekEnd)
            ->pending()
            ->get();

        return [
            'week_start' => $weekStart->format('Y-m-d'),
            'week_end' => $weekEnd->format('Y-m-d'),
            'total_amount_ttc' => round($payments->sum('net_amount_ttc'), 2),
            'payment_count' => $payments->count(),
            'payments' => $payments,
        ];
    }

    /**
     * Generate monthly invoice.
     *
     * @param int $vendorId
     * @param int $month
     * @param int $year
     * @return VendorMonthlyInvoice
     */
    public function generateMonthlyInvoice(int $vendorId, int $month, int $year): VendorMonthlyInvoice
    {
        $monthStart = Carbon::create($year, $month, 1)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        $existingInvoice = VendorMonthlyInvoice::where('vendor_id', $vendorId)
            ->where('year', $year)
            ->whereMonth('month', $month)
            ->first();

        if ($existingInvoice) {
            return $existingInvoice;
        }

        $payments = VendorPayment::byVendor($vendorId)
            ->whereBetween('payment_date', [$monthStart, $monthEnd])
            ->get();

        $totalCommissionExclVat = $payments->sum('commission_amount_excl_vat');
        $totalVat = $payments->sum('commission_vat');
        $totalCommissionInclVat = $payments->sum('commission_amount_incl_vat');

        $invoiceNumber = VendorMonthlyInvoice::generateInvoiceNumber($vendorId, $year, $month);

        $invoice = VendorMonthlyInvoice::create([
            'vendor_id' => $vendorId,
            'month' => $monthStart->toDateString(),
            'year' => $year,
            'total_commission_excl_vat' => round($totalCommissionExclVat, 2),
            'total_vat' => round($totalVat, 2),
            'total_commission_incl_vat' => round($totalCommissionInclVat, 2),
            'invoice_number' => $invoiceNumber,
            'generated_at' => now(),
        ]);

        return $invoice;
    }

    /**
     * Get vendor statistics.
     *
     * @param int $vendorId
     * @return array
     */
    public function getVendorStatistics(int $vendorId): array
    {
        $allPayments = VendorPayment::byVendor($vendorId)->get();

        return [
            'total_revenue' => round($allPayments->sum('total_amount_ttc'), 2),
            'total_commission_paid' => round($allPayments->sum('commission_amount_incl_vat'), 2),
            'total_reimbursed' => round($allPayments->where('transfer_status', 'processed')->sum('net_amount_ttc'), 2),
            'pending_reimbursement' => round($allPayments->where('transfer_status', 'pending')->sum('net_amount_ttc'), 2),
            'total_transactions' => $allPayments->count(),
            'full_payments_count' => $allPayments->where('payment_type', 'full')->count(),
            'partial_payments_count' => $allPayments->where('payment_type', 'partial')->count(),
        ];
    }

    /**
     * Create monthly invoice with detailed information.
     *
     * @param int $vendorId
     * @param int $month
     * @param int $year
     * @return array
     */
    public function createMonthlyInvoice(int $vendorId, int $month, int $year): array
    {
        $monthStart = Carbon::create($year, $month, 1)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        $payments = VendorPayment::byVendor($vendorId)
            ->whereBetween('payment_date', [$monthStart, $monthEnd])
            ->with(['order', 'reservation'])
            ->get();

        $totalRevenue = $payments->sum('total_amount_ttc');
        $totalCommissionExclVat = $payments->sum('commission_amount_excl_vat');
        $totalVat = $payments->sum('commission_vat');
        $totalCommissionInclVat = $payments->sum('commission_amount_incl_vat');
        $totalNetAmount = $payments->sum('net_amount_ttc');

        $fullPayments = $payments->where('payment_type', 'full');
        $partialPayments = $payments->where('payment_type', 'partial');

        $invoice = $this->generateMonthlyInvoice($vendorId, $month, $year);

        return [
            'invoice' => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'month' => $month,
                'year' => $year,
                'month_name' => $monthStart->format('F Y'),
                'generated_at' => $invoice->generated_at->format('Y-m-d H:i:s'),
                'pdf_path' => $invoice->pdf_path,
            ],
            'summary' => [
                'total_revenue_earned' => round($totalRevenue, 2),
                'total_commission_deducted' => [
                    'excl_vat' => round($totalCommissionExclVat, 2),
                    'vat' => round($totalVat, 2),
                    'incl_vat' => round($totalCommissionInclVat, 2),
                ],
                'net_amount_received' => round($totalNetAmount, 2),
            ],
            'breakdown' => [
                'full_payments' => [
                    'count' => $fullPayments->count(),
                    'total_revenue' => round($fullPayments->sum('total_amount_ttc'), 2),
                    'commission_deducted' => round($fullPayments->sum('commission_amount_incl_vat'), 2),
                    'net_amount' => round($fullPayments->sum('net_amount_ttc'), 2),
                ],
                'partial_payments' => [
                    'count' => $partialPayments->count(),
                    'total_revenue' => round($partialPayments->sum('total_amount_ttc'), 2),
                    'commission_deducted' => round($partialPayments->sum('commission_amount_incl_vat'), 2),
                    'net_amount' => round($partialPayments->sum('net_amount_ttc'), 2),
                ],
            ],
            'transactions' => $payments->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'payment_date' => $payment->payment_date->format('Y-m-d'),
                    'payment_type' => $payment->payment_type,
                    'order_id' => $payment->order_id,
                    'reservation_id' => $payment->reservation_id,
                    'total_amount_ttc' => round($payment->total_amount_ttc, 2),
                    'commission_rate' => round($payment->commission_rate_applied, 2),
                    'commission_deducted' => round($payment->commission_amount_incl_vat, 2),
                    'net_amount' => round($payment->net_amount_ttc, 2),
                    'transfer_status' => $payment->transfer_status,
                ];
            })->values(),
            'period' => [
                'start_date' => $monthStart->format('Y-m-d'),
                'end_date' => $monthEnd->format('Y-m-d'),
            ],
        ];
    }
}


