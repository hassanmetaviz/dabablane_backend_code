<?php

namespace App\Services;

use App\Models\VendorCommission;
use App\Models\CommissionSettings;
use App\Models\User;
use App\Models\Category;
use App\Models\VendorPayment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CommissionCalculationService
{
    protected $settings;

    public function __construct()
    {
        $this->settings = CommissionSettings::getSettings();
    }

    /**
     * Calculate commission for a payment.
     *
     * @param float $totalTtc Total amount TTC
     * @param string $paymentType 'full' or 'partial'
     * @param int $vendorId Vendor ID
     * @param int $categoryId Category ID
     * @return array
     */
    public function calculateCommission(float $totalTtc, string $paymentType, int $vendorId, int $categoryId): array
    {
        $commissionRate = $this->getCommissionRate($vendorId, $categoryId, $paymentType);

        $commissionExclVat = $totalTtc * ($commissionRate / 100);

        $commissionVat = $commissionExclVat * ($this->settings->vat_rate / 100);

        $commissionInclVat = $commissionExclVat + $commissionVat;

        $netAmountTtc = $totalTtc - $commissionInclVat;

        return [
            'commission_rate' => $commissionRate,
            'commission_excl_vat' => round($commissionExclVat, 2),
            'commission_vat' => round($commissionVat, 2),
            'commission_incl_vat' => round($commissionInclVat, 2),
            'net_amount_ttc' => round($netAmountTtc, 2),
        ];
    }

    /**
     * Get the effective commission rate for a vendor and category.
     *
     * @param int $vendorId
     * @param int $categoryId
     * @param string $paymentType 'full' or 'partial'
     * @return float
     */
    public function getCommissionRate(int $vendorId, int $categoryId, string $paymentType = 'full'): float
    {

        $vendor = User::find($vendorId);
        if ($vendor && $vendor->custom_commission_rate !== null) {
            $baseRate = $vendor->custom_commission_rate;
        } else {

            $vendorCommission = VendorCommission::where('category_id', $categoryId)
                ->where('vendor_id', $vendorId)
                ->where('is_active', true)
                ->first();

            if ($vendorCommission) {
                $baseRate = $vendorCommission->commission_rate;
            } else {

                $category = Category::find($categoryId);
                if ($category && $category->default_commission_rate !== null) {
                    $baseRate = $category->default_commission_rate;
                } else {

                    $categoryCommission = VendorCommission::where('category_id', $categoryId)
                        ->whereNull('vendor_id')
                        ->where('is_active', true)
                        ->first();

                    $baseRate = $categoryCommission ? $categoryCommission->commission_rate : 0;
                }
            }
        }

        if ($paymentType === 'partial') {
            $partialRate = $this->settings->partial_payment_commission_rate;
            if ($partialRate > 0) {
                if ($partialRate < $baseRate) {
                    return (float) $partialRate;
                } else {
                    return (float) ($baseRate * ($partialRate / 100));
                }
            }
            return (float) ($baseRate * 0.5);
        }

        return (float) $baseRate;
    }

    /**
     * Create vendor payment record when payment is received.
     *
     * @param array $paymentData
     * @return VendorPayment
     */
    public function calculateVendorPayment(array $paymentData): VendorPayment
    {
        $calculation = $this->calculateCommission(
            $paymentData['total_amount_ttc'],
            $paymentData['payment_type'],
            $paymentData['vendor_id'],
            $paymentData['category_id']
        );

        $weekRange = VendorPayment::getWeekRange(
            isset($paymentData['payment_date']) ? Carbon::parse($paymentData['payment_date']) : null
        );

        $vendor = User::find($paymentData['vendor_id']);
        $creditAccount = $vendor->rib_account ?? $vendor->ribUrl ?? null;

        return VendorPayment::create([
            'vendor_id' => $paymentData['vendor_id'],
            'order_id' => $paymentData['order_id'] ?? null,
            'reservation_id' => $paymentData['reservation_id'] ?? null,
            'total_amount_ttc' => $paymentData['total_amount_ttc'],
            'payment_type' => $paymentData['payment_type'],
            'commission_rate_applied' => $calculation['commission_rate'],
            'commission_amount_excl_vat' => $calculation['commission_excl_vat'],
            'commission_vat' => $calculation['commission_vat'],
            'commission_amount_incl_vat' => $calculation['commission_incl_vat'],
            'net_amount_ttc' => $calculation['net_amount_ttc'],
            'transfer_status' => 'pending',
            'debit_account' => $this->settings->daba_blane_account_iban
                ? 'DabaBlane corporate account – ' . $this->settings->daba_blane_account_iban
                : 'DabaBlane corporate account – [IBAN]',
            'credit_account' => $creditAccount,
            'reason' => 'Reimbursement of payment via DabaBlane platform net of platform commission',
            'booking_date' => $paymentData['booking_date'] ?? Carbon::now()->toDateString(),
            'payment_date' => $paymentData['payment_date'] ?? Carbon::now()->toDateString(),
            'week_start' => $weekRange['week_start']->toDateString(),
            'week_end' => $weekRange['week_end']->toDateString(),
        ]);
    }
}

