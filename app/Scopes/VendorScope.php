<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;
use App\Services\VendorContext;

class VendorScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * Business Rules:
     * - Vendors: Only see records where vendor_id = their user ID (strict isolation)
     * - Admins: See ALL records (no filtering - can manage platform + vendor resources)
     * - Unauthenticated/Users: No scope applied (handled by route-level access)
     */
    public function apply(Builder $builder, Model $model): void
    {
        // Check if global scope is enabled via config
        if (!config('multitenancy.global_scope_enabled', true)) {
            return;
        }

        // Only apply for authenticated users
        if (!Auth::check()) {
            return;
        }

        $user = Auth::user();

        // Admin users bypass the scope - can see all including platform resources
        if ($user->hasRole('admin')) {
            return;
        }

        // Vendors ONLY see their own resources (strict isolation)
        if ($user->hasRole('vendor')) {
            $vendorId = VendorContext::getVendorId() ?? $user->id;
            $builder->where($model->getTable() . '.vendor_id', $vendorId);
        }
    }

    /**
     * Extend the query builder with the needed functions.
     */
    public function extend(Builder $builder): void
    {
        // Add method to bypass vendor scope when needed (for admin operations)
        $builder->macro('withoutVendorScope', function (Builder $builder) {
            return $builder->withoutGlobalScope(VendorScope::class);
        });

        // Add method to filter by specific vendor (for admin viewing specific vendor data)
        $builder->macro('forSpecificVendor', function (Builder $builder, int $vendorId) {
            return $builder->withoutGlobalScope(VendorScope::class)
                          ->where($builder->getModel()->getTable() . '.vendor_id', $vendorId);
        });

        // Add method to get only platform resources (no vendor_id)
        $builder->macro('platformOnly', function (Builder $builder) {
            return $builder->withoutGlobalScope(VendorScope::class)
                          ->whereNull($builder->getModel()->getTable() . '.vendor_id');
        });

        // Add method to get only vendor resources (has vendor_id)
        $builder->macro('vendorOnly', function (Builder $builder) {
            return $builder->withoutGlobalScope(VendorScope::class)
                          ->whereNotNull($builder->getModel()->getTable() . '.vendor_id');
        });
    }
}
