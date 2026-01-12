<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendorCommission extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'vendor_id',
        'commission_rate',
        'partial_commission_rate',
        'is_active',
    ];

    protected $casts = [
        'commission_rate' => 'decimal:2',
        'partial_commission_rate' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Get the category that owns the commission.
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the vendor (if vendor-specific override).
     */
    public function vendor()
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    /**
     * Scope a query to only include active commissions.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope by category.
     */
    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    /**
     * Scope by vendor.
     */
    public function scopeByVendor($query, $vendorId)
    {
        return $query->where('vendor_id', $vendorId);
    }

    /**
     * Get the commission rate for a payment type.
     */
    public function getCommissionRate(string $paymentType = 'full'): float
    {
        if ($paymentType === 'partial') {
            return (float) ($this->partial_commission_rate ?? ($this->commission_rate / 2));
        }
        return (float) $this->commission_rate;
    }

    /**
     * Get the partial commission rate.
     */
    public function getPartialRate(): float
    {
        return $this->partial_commission_rate ?? ($this->commission_rate / 2);
    }
}

