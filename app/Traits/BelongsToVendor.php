<?php

namespace App\Traits;

use App\Scopes\VendorScope;
use App\Services\VendorContext;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

trait BelongsToVendor
{
    /**
     * Boot the trait
     */
    public static function bootBelongsToVendor(): void
    {
        // Register the global scope if enabled
        if (config('multitenancy.global_scope_enabled', true)) {
            static::addGlobalScope(new VendorScope);
        }

        // Auto-assign vendor_id on creation ONLY for vendor users
        // Admins can create platform-wide resources (no vendor_id)
        static::creating(function ($model) {
            if (empty($model->vendor_id)) {
                $vendorId = VendorContext::getVendorId();
                // Only set vendor_id if user is a vendor (not admin)
                if ($vendorId && auth()->check() && auth()->user()->hasRole('vendor')) {
                    $model->vendor_id = $vendorId;
                }
            }
        });
    }

    /**
     * Get the vendor that owns the model
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_id')
            ->select('id', 'company_name', 'name', 'email', 'isDiamond', 'status',
                     'landline', 'phone', 'address', 'city', 'district',
                     'subdistrict', 'logoUrl', 'blane_limit');
    }

    /**
     * Check if the model belongs to a specific vendor
     */
    public function belongsToVendor(int $vendorId): bool
    {
        return $this->vendor_id === $vendorId;
    }

    /**
     * Check if the authenticated user owns this resource
     */
    public function isOwnedByCurrentVendor(): bool
    {
        $vendorId = VendorContext::getVendorId();
        return $vendorId && $this->vendor_id === $vendorId;
    }

    /**
     * Check if this is a platform resource (no vendor)
     */
    public function isPlatformResource(): bool
    {
        return $this->vendor_id === null;
    }

    /**
     * Check if this is a vendor resource
     */
    public function isVendorResource(): bool
    {
        return $this->vendor_id !== null;
    }

    /**
     * Scope to query without vendor filtering (for admin use)
     */
    public function scopeWithoutVendorFilter($query)
    {
        return $query->withoutGlobalScope(VendorScope::class);
    }

    /**
     * Scope for platform-only resources (admin-created, no vendor)
     */
    public function scopePlatformOnly($query)
    {
        return $query->withoutGlobalScope(VendorScope::class)
                    ->whereNull('vendor_id');
    }

    /**
     * Scope for vendor-owned resources only
     */
    public function scopeVendorOnly($query)
    {
        return $query->withoutGlobalScope(VendorScope::class)
                    ->whereNotNull('vendor_id');
    }

    /**
     * Scope to filter by a specific vendor
     */
    public function scopeForSpecificVendor($query, int $vendorId)
    {
        return $query->withoutGlobalScope(VendorScope::class)
                    ->where('vendor_id', $vendorId);
    }

    /**
     * Check ownership with legacy commerce_name support
     */
    public function isOwnedBy($user): bool
    {
        // Primary check: vendor_id
        if ($this->vendor_id !== null && $this->vendor_id === $user->id) {
            return true;
        }

        // Legacy fallback: commerce_name (if enabled)
        if (config('multitenancy.legacy_commerce_name_support', true)) {
            if ($this->vendor_id === null &&
                isset($this->commerce_name) &&
                $this->commerce_name === $user->company_name) {
                return true;
            }
        }

        return false;
    }
}
