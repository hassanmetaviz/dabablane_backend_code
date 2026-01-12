<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use App\Traits\BelongsToVendor;

class Reservation extends Model
{
    use BelongsToVendor;
    protected $fillable = [
        'vendor_id',
        'blane_id',
        'customers_id',
        'payment_method',
        'total_price',
        'partiel_price',
        'date',
        'phone',
        'time',
        'comments',
        'NUM_RES',
        'status',
        'number_persons',
        'quantity',
        'end_date',
        'cancel_token',
        'cancel_token_created_at',
        'source'
    ];

    protected $dates = [
        'created_at',
        'updated_at',
        'date',
        'end_date',
        'cancel_token_created_at'
    ];


    const STATUS_WAITING = 'waiting';
    const STATUS_CLIENT_CONFIRMED = 'client_confirmed';
    const STATUS_RETAILER_CONFIRMED = 'retailer_confirmed';
    const STATUS_ADMIN_CONFIRMED = 'admin_confirmed';
    const STATUS_CLIENT_CANCELLED = 'client_cancelled';
    const STATUS_RETAILER_CANCELLED = 'retailer_cancelled';
    const STATUS_ADMIN_CANCELLED = 'admin_cancelled';
    const STATUS_CANCELLED_CLIENT_NO_RESPONSE = 'cancelled_client_no_response';
    const STATUS_CANCELLED_RETAILER_NO_RESPONSE = 'cancelled_retailer_no_response';
    const STATUS_ESCALATED_ADMIN_RETAILER_NO_RESPONSE = 'escalated_admin';
    const STATUS_ADMIN_GIVE_UP_WAITING = 'admin_give_up';
    // Legacy statuses
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_PENDING = 'pending';
    const STATUS_SHIPPED = 'shipped';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_PAID = 'paid';
    const STATUS_FAILED = 'failed';

    /**
     * Get all available status options
     */
    public static function getStatusOptions(): array
    {
        return [
            self::STATUS_WAITING,
            self::STATUS_CLIENT_CONFIRMED,
            self::STATUS_RETAILER_CONFIRMED,
            self::STATUS_ADMIN_CONFIRMED,
            self::STATUS_CLIENT_CANCELLED,
            self::STATUS_RETAILER_CANCELLED,
            self::STATUS_ADMIN_CANCELLED,
            self::STATUS_CANCELLED_CLIENT_NO_RESPONSE,
            self::STATUS_CANCELLED_RETAILER_NO_RESPONSE,
            self::STATUS_ESCALATED_ADMIN_RETAILER_NO_RESPONSE,
            self::STATUS_ADMIN_GIVE_UP_WAITING,
            self::STATUS_CONFIRMED,
            self::STATUS_PENDING,
            self::STATUS_SHIPPED,
            self::STATUS_CANCELLED,
            self::STATUS_PAID,
            self::STATUS_FAILED,
        ];
    }

    /**
     * Check if status is a confirmed status
     */
    public function isConfirmed(): bool
    {
        return in_array($this->status, [
            self::STATUS_CLIENT_CONFIRMED,
            self::STATUS_RETAILER_CONFIRMED,
            self::STATUS_ADMIN_CONFIRMED,
            self::STATUS_CONFIRMED,
        ]);
    }

    /**
     * Check if status is a cancelled status
     */
    public function isCancelled(): bool
    {
        return in_array($this->status, [
            self::STATUS_CLIENT_CANCELLED,
            self::STATUS_RETAILER_CANCELLED,
            self::STATUS_ADMIN_CANCELLED,
            self::STATUS_CANCELLED_CLIENT_NO_RESPONSE,
            self::STATUS_CANCELLED_RETAILER_NO_RESPONSE,
            self::STATUS_ADMIN_GIVE_UP_WAITING,
            self::STATUS_CANCELLED,
        ]);
    }

    /**
     * Check if status is a waiting status
     */
    public function isWaiting(): bool
    {
        return in_array($this->status, [
            self::STATUS_WAITING,
            self::STATUS_PENDING,
        ]);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($reservation) {
            // Auto-assign vendor_id from blane
            if ($reservation->blane_id && !$reservation->vendor_id) {
                $blane = Blane::find($reservation->blane_id);
                if ($blane && $blane->vendor_id) {
                    $reservation->vendor_id = $blane->vendor_id;
                }
            }

            // Existing cancel_token logic
            if (empty($reservation->cancel_token)) {
                $baseString = $reservation->NUM_RES . '|' . now()->timestamp . '|' . Str::random(16);
                $reservation->cancel_token = hash('sha256', $baseString);
                $reservation->cancel_token_created_at = now();
            }
        });
    }

    /**
     * Verify if a cancellation request is valid based on token and timestamp
     *
     * @param string $token Cancel token from request
     * @param string $timestamp Timestamp from request
     * @return bool
     */
    public function verifyCancellationRequest(string $token, string $timestamp): bool
    {
        if ($this->cancel_token_created_at) {
            $hoursElapsed = now()->diffInHours($this->cancel_token_created_at);
            if ($hoursElapsed > 1) {
                return false;
            }
        }

        $requestTime = (int) $timestamp;
        $currentTime = now()->timestamp;
        $timeLimit = 900;

        if ($currentTime - $requestTime > $timeLimit) {
            return false;
        }

        $verificationHash = hash('sha256', $this->cancel_token . '|' . $timestamp);
        return hash_equals($verificationHash, $token);
    }

    public function blane()
    {
        return $this->belongsTo(Blane::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customers::class, 'customers_id');
    }

    public function vendor()
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    /**
     * Scope a query to filter by vendor.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  int|null  $vendorId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForVendor($query, $vendorId = null)
    {
        $vendorId = $vendorId ?? (auth()->check() && auth()->user()->hasRole('vendor') ? auth()->id() : null);

        if ($vendorId) {
            return $query->where('vendor_id', $vendorId);
        }

        return $query;
    }
}
