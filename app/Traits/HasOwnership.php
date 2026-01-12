<?php

namespace App\Traits;

trait HasOwnership
{
    /**
     * Check if the authenticated user owns this resource.
     * Supports both vendor_id (new way) and commerce_name (legacy)
     */
    public function isOwnedBy($user): bool
    {
        // New way: Check by vendor_id
        if (isset($this->vendor_id) && $this->vendor_id === $user->id) {
            return true;
        }

        // Legacy way: Check by commerce_name for backward compatibility
        if (isset($this->vendor_id) && $this->vendor_id === null && 
            isset($this->commerce_name) && $this->commerce_name === $user->company_name) {
            return true;
        }

        return false;
    }
}




