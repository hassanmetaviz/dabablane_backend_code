<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class VendorPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_id',
        'order_id',
        'reservation_id',
        'total_amount_ttc',
        'payment_type',
        'commission_rate_applied',
        'commission_amount_excl_vat',
        'commission_vat',
        'commission_amount_incl_vat',
        'net_amount_ttc',
        'transfer_status',
        'transfer_date',
        'debit_account',
        'credit_account',
        'reason',
        'booking_date',
        'payment_date',
        'week_start',
        'week_end',
        'updated_by',
    ];

    protected $casts = [
        'total_amount_ttc' => 'decimal:2',
        'commission_rate_applied' => 'decimal:2',
        'commission_amount_excl_vat' => 'decimal:2',
        'commission_vat' => 'decimal:2',
        'commission_amount_incl_vat' => 'decimal:2',
        'net_amount_ttc' => 'decimal:2',
        'transfer_date' => 'datetime',
        'booking_date' => 'date',
        'payment_date' => 'date',
        'week_start' => 'date',
        'week_end' => 'date',
    ];

    /**
     * Get the vendor that owns the payment.
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    /**
     * Get the order associated with the payment.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the reservation associated with the payment.
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /**
     * Get the admin who updated the payment.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get payment logs.
     */
    public function logs()
    {
        return $this->hasMany(VendorPaymentLog::class);
    }

    /**
     * Scope a query to only include pending payments.
     */
    public function scopePending($query)
    {
        return $query->where('transfer_status', 'pending');
    }

    /**
     * Scope a query to only include processed payments.
     */
    public function scopeProcessed($query)
    {
        return $query->where('transfer_status', 'processed');
    }

    /**
     * Scope a query to only include complete payments.
     */
    public function scopeComplete($query)
    {
        return $query->where('transfer_status', 'complete');
    }

    /**
     * Scope by vendor.
     */
    public function scopeByVendor($query, $vendorId)
    {
        return $query->where('vendor_id', $vendorId);
    }

    /**
     * Scope by payment type.
     */
    public function scopeByPaymentType($query, $type)
    {
        return $query->where('payment_type', $type);
    }

    /**
     * Scope by week range.
     */
    public function scopeByWeek($query, Carbon $weekStart, Carbon $weekEnd)
    {
        return $query->whereBetween('week_start', [$weekStart, $weekEnd]);
    }

    /**
     * Get week range for a given date.
     */
    public static function getWeekRange(Carbon $date = null): array
    {
        $date = $date ?? Carbon::now();
        $weekStart = $date->copy()->startOfWeek(Carbon::MONDAY);
        $weekEnd = $date->copy()->endOfWeek(Carbon::SUNDAY);

        return [
            'week_start' => $weekStart,
            'week_end' => $weekEnd,
        ];
    }

    /**
     * Mark payment as processed.
     */
    public function markAsProcessed(int $adminId, ?Carbon $transferDate = null, ?string $note = null): void
    {
        $this->update([
            'transfer_status' => 'processed',
            'transfer_date' => $transferDate ?? Carbon::now(),
            'updated_by' => $adminId,
        ]);

        // Create audit log
        VendorPaymentLog::create([
            'vendor_payment_id' => $this->id,
            'admin_id' => $adminId,
            'action' => 'marked_processed',
            'previous_status' => 'pending',
            'new_status' => 'processed',
            'admin_note' => $note,
            'created_at' => now(),
        ]);
    }

    /**
     * Revert payment to pending.
     */
    public function revertToPending(int $adminId, string $note): void
    {
        $previousStatus = $this->transfer_status;

        $this->update([
            'transfer_status' => 'pending',
            'transfer_date' => null,
            'updated_by' => $adminId,
        ]);

        // Create audit log
        VendorPaymentLog::create([
            'vendor_payment_id' => $this->id,
            'admin_id' => $adminId,
            'action' => 'reverted_to_pending',
            'previous_status' => $previousStatus,
            'new_status' => 'pending',
            'admin_note' => $note,
            'created_at' => now(),
        ]);
    }
}


