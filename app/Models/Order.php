<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use App\Traits\BelongsToVendor;

class Order extends Model
{
    use BelongsToVendor;
    protected $fillable = [
        'vendor_id',
        'blane_id',
        'customers_id',
        'payment_method',
        'partiel_price',
        'NUM_ORD',
        'phone',
        'quantity',
        'delivery_address',
        'total_price',
        'status',
        'cancel_token',
        'cancel_token_created_at',
        'comments',
        'source'
    ];

    protected $dates = [
        'created_at',
        'updated_at',
        'cancel_token_created_at'
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($order) {
            // Auto-assign vendor_id from blane
            if ($order->blane_id && !$order->vendor_id) {
                $blane = Blane::find($order->blane_id);
                if ($blane && $blane->vendor_id) {
                    $order->vendor_id = $blane->vendor_id;
                }
            }

            // Existing cancel_token logic
            if (empty($order->cancel_token)) {
                $baseString = $order->NUM_ORD . '|' . now()->timestamp . '|' . Str::random(16);
                $order->cancel_token = hash('sha256', $baseString);
                $order->cancel_token_created_at = now();
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

    public function shippingDetails()
    {
        return $this->hasOne(ShippingDetail::class);
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
