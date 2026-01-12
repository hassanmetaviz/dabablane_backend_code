<?php

namespace App\Policies;

use App\Models\Blane;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class BlanePolicy
{
    use HandlesAuthorization;

    /**
     * Determine if the user can view any blanes.
     */
    public function viewAny(User $user): bool
    {
        // Admins can view all
        if ($user->hasRole('admin')) {
            return true;
        }

        // Vendors and regular users can view blanes
        return $user->hasAnyRole(['vendor', 'user']);
    }

    /**
     * Determine if the user can view the blane.
     */
    public function view(User $user, Blane $blane): bool
    {
        // Admins can view all
        if ($user->hasRole('admin')) {
            return true;
        }

        // Vendors can view their own blanes or blanes without vendor_id
        if ($user->hasRole('vendor')) {
            return $this->isOwner($user, $blane);
        }

        // Regular users can view public blanes
        return true;
    }

    /**
     * Determine if the user can create blanes.
     */
    public function create(User $user): bool
    {
        // Only vendors and admins can create blanes
        return $user->hasAnyRole(['vendor', 'admin']);
    }

    /**
     * Determine if the user can update the blane.
     */
    public function update(User $user, Blane $blane): bool
    {
        // Admins can update all
        if ($user->hasRole('admin')) {
            return true;
        }

        // Vendors can only update their own blanes
        if ($user->hasRole('vendor')) {
            return $this->isOwner($user, $blane);
        }

        return false;
    }

    /**
     * Determine if the user can delete the blane.
     */
    public function delete(User $user, Blane $blane): bool
    {
        // Admins can delete all
        if ($user->hasRole('admin')) {
            return true;
        }

        // Vendors can only delete their own blanes
        if ($user->hasRole('vendor')) {
            return $this->isOwner($user, $blane);
        }

        return false;
    }

    /**
     * Determine if the user can update blane status.
     */
    public function updateStatus(User $user, Blane $blane): bool
    {
        // Admins can update status of all blanes
        if ($user->hasRole('admin')) {
            return true;
        }

        // Vendors can only update status of their own blanes
        if ($user->hasRole('vendor')) {
            return $this->isOwner($user, $blane);
        }

        return false;
    }

    /**
     * Check if user owns the blane.
     * Supports both vendor_id (new way) and commerce_name (legacy/backward compatibility)
     */
    private function isOwner(User $user, Blane $blane): bool
    {
        // New way: Check by vendor_id
        if ($blane->vendor_id && $blane->vendor_id === $user->id) {
            return true;
        }

        // Legacy way: Check by commerce_name for backward compatibility
        if ($blane->vendor_id === null && $blane->commerce_name === $user->company_name) {
            return true;
        }

        return false;
    }
}


