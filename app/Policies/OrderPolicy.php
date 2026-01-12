<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy extends VendorPolicy
{
    /**
     * Determine if the user can view any orders.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'vendor']);
    }

    /**
     * Determine if the user can view the order.
     */
    public function view(User $user, $order): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        // Vendors can only view orders for their blanes
        if ($user->hasRole('vendor')) {
            return $order->vendor_id === $user->id;
        }

        return false;
    }

    /**
     * Determine if the user can create orders.
     * Both admin and vendor can create orders.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'vendor', 'user']);
    }

    /**
     * Determine if the user can update the order.
     */
    public function update(User $user, $order): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->hasRole('vendor') && $order->vendor_id === $user->id;
    }

    /**
     * Determine if the user can update order status.
     */
    public function updateStatus(User $user, Order $order): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->hasRole('vendor') && $order->vendor_id === $user->id;
    }

    /**
     * Determine if the user can delete the order.
     * Only admins can delete orders.
     */
    public function delete(User $user, $order): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine if the user can restore the order.
     */
    public function restore(User $user, Order $order): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine if the user can permanently delete the order.
     */
    public function forceDelete(User $user, Order $order): bool
    {
        return $user->hasRole('admin');
    }
}
