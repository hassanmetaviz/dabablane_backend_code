<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Database\Eloquent\Model;

abstract class VendorPolicy
{
    use HandlesAuthorization;

    /**
     * Determine if the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'vendor', 'user']);
    }

    /**
     * Determine if the user can view the model.
     */
    public function view(User $user, Model $model): bool
    {
        // Admins can view all
        if ($user->hasRole('admin')) {
            return true;
        }

        // Vendors can only view their own resources
        if ($user->hasRole('vendor')) {
            return $this->isOwner($user, $model);
        }

        // Regular users: public access
        return true;
    }

    /**
     * Determine if the user can create models.
     * Both admin and vendor can create.
     * Admin creates platform resources (no vendor_id).
     * Vendor creates vendor resources (auto-assigned vendor_id).
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'vendor']);
    }

    /**
     * Determine if the user can update the model.
     */
    public function update(User $user, Model $model): bool
    {
        // Admins can update all
        if ($user->hasRole('admin')) {
            return true;
        }

        // Vendors can only update their own resources
        return $user->hasRole('vendor') && $this->isOwner($user, $model);
    }

    /**
     * Determine if the user can delete the model.
     */
    public function delete(User $user, Model $model): bool
    {
        // Admins can delete all
        if ($user->hasRole('admin')) {
            return true;
        }

        // Vendors can only delete their own resources
        return $user->hasRole('vendor') && $this->isOwner($user, $model);
    }

    /**
     * Check if user owns the model.
     * Supports both vendor_id (new way) and commerce_name (legacy)
     */
    protected function isOwner(User $user, Model $model): bool
    {
        // Primary check: vendor_id
        if (isset($model->vendor_id) && $model->vendor_id === $user->id) {
            return true;
        }

        // Platform resources (no vendor_id) can only be managed by admin
        if (!isset($model->vendor_id) || $model->vendor_id === null) {
            // Legacy fallback: commerce_name for backward compatibility
            if (config('multitenancy.legacy_commerce_name_support', true)) {
                if (isset($model->commerce_name) && $model->commerce_name === $user->company_name) {
                    return true;
                }
            }
            return false;
        }

        return false;
    }

    /**
     * Check if the model is a platform resource (no vendor)
     */
    protected function isPlatformResource(Model $model): bool
    {
        return !isset($model->vendor_id) || $model->vendor_id === null;
    }
}
