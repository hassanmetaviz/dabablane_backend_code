<?php

namespace App\Policies;

use App\Models\Rating;
use App\Models\User;

class RatingPolicy extends VendorPolicy
{
    /**
     * Determine if the user can view any ratings.
     */
    public function viewAny(User $user): bool
    {
        return true; // Everyone can view ratings
    }

    /**
     * Determine if the user can view the rating.
     */
    public function view(User $user, $rating): bool
    {
        return true; // Ratings are public
    }

    /**
     * Determine if the user can create ratings.
     * Any authenticated user can create ratings.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'user', 'vendor']);
    }

    /**
     * Determine if the user can update the rating.
     * Only admin can update ratings.
     */
    public function update(User $user, $rating): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine if the user can delete the rating.
     * Only admin can delete ratings.
     */
    public function delete(User $user, $rating): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine if vendor can view ratings for their blanes.
     */
    public function viewForVendor(User $user, Rating $rating): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->hasRole('vendor') && $rating->vendor_id === $user->id;
    }

    /**
     * Determine if the user can respond to a rating.
     */
    public function respond(User $user, Rating $rating): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        // Vendors can respond to ratings on their blanes
        return $user->hasRole('vendor') && $rating->vendor_id === $user->id;
    }
}
