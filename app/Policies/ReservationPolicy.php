<?php

namespace App\Policies;

use App\Models\Reservation;
use App\Models\User;

class ReservationPolicy extends VendorPolicy
{
    /**
     * Determine if the user can view any reservations.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'vendor']);
    }

    /**
     * Determine if the user can view the reservation.
     */
    public function view(User $user, $reservation): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        // Vendors can only view reservations for their blanes
        if ($user->hasRole('vendor')) {
            return $reservation->vendor_id === $user->id;
        }

        return false;
    }

    /**
     * Determine if the user can create reservations.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'vendor', 'user']);
    }

    /**
     * Determine if the user can update the reservation.
     */
    public function update(User $user, $reservation): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->hasRole('vendor') && $reservation->vendor_id === $user->id;
    }

    /**
     * Determine if the user can update reservation status.
     */
    public function updateStatus(User $user, Reservation $reservation): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->hasRole('vendor') && $reservation->vendor_id === $user->id;
    }

    /**
     * Determine if the user can delete the reservation.
     * Only admins can delete reservations.
     */
    public function delete(User $user, $reservation): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine if the user can restore the reservation.
     */
    public function restore(User $user, Reservation $reservation): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine if the user can permanently delete the reservation.
     */
    public function forceDelete(User $user, Reservation $reservation): bool
    {
        return $user->hasRole('admin');
    }
}
