<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'plan_id',
        'promo_code_id',
        'plan_price_ht',
        'discount_amount',
        'subtotal_ht',
        'vat_amount',
        'total_ttc',
        'start_date',
        'end_date',
        'payment_method',
        'status',
        'cmi_transaction_id',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_COMPLETED = 'completed';
    const STATUS_EXPIRED = 'expired';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_MANUAL = 'manual';
    const STATUS_FAILED = 'failed';

    /**
     * Check if subscription is active
     */
    public function isActive()
    {
        return $this->status === self::STATUS_COMPLETED
            && $this->start_date <= now()
            && $this->end_date > now();
    }

    /**
     * Check if subscription is expired
     */
    public function isExpired()
    {
        return $this->status === self::STATUS_EXPIRED
            || ($this->status === self::STATUS_COMPLETED && $this->end_date <= now());
    }

    /**
     * Get days remaining until expiration
     */
    public function getDaysRemaining()
    {
        if ($this->isExpired()) {
            return 0;
        }

        return max(0, now()->diffInDays($this->end_date, false));
    }

    /**
     * Check if subscription expires soon (within specified days)
     */
    public function expiresSoon($days = 7)
    {
        return $this->isActive() && $this->getDaysRemaining() <= $days;
    }

    /**
     * Scope for active subscriptions
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_COMPLETED)
            ->where('start_date', '<=', now())
            ->where('end_date', '>', now());
    }

    /**
     * Scope for expired subscriptions
     */
    public function scopeExpired($query)
    {
        return $query->where(function ($q) {
            $q->where('status', self::STATUS_EXPIRED)
                ->orWhere(function ($subQ) {
                    $subQ->where('status', self::STATUS_COMPLETED)
                        ->where('end_date', '<=', now());
                });
        });
    }

    /**
     * Scope for expiring soon subscriptions
     */
    public function scopeExpiringSoon($query, $days = 7)
    {
        $expiringDate = now()->addDays($days);
        return $query->where('status', self::STATUS_COMPLETED)
            ->where('end_date', '<=', $expiringDate)
            ->where('end_date', '>', now());
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function promoCode()
    {
        return $this->belongsTo(PromoCode::class);
    }

    public function addOns()
    {
        return $this->belongsToMany(AddOn::class, 'purchase_add_ons')
            ->withPivot('quantity', 'unit_price_ht', 'total_price_ht')
            ->withTimestamps();
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class);
    }
}