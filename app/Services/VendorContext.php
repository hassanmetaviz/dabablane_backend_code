<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;

class VendorContext
{
    /**
     * The current vendor ID in context
     */
    protected static ?int $vendorId = null;

    /**
     * Whether vendor context is enforced
     */
    protected static bool $enforced = false;

    /**
     * Set the vendor context
     */
    public static function set(?int $vendorId): void
    {
        static::$vendorId = $vendorId;
    }

    /**
     * Get the current vendor ID
     */
    public static function getVendorId(): ?int
    {
        if (static::$vendorId !== null) {
            return static::$vendorId;
        }

        if (Auth::check() && Auth::user()->hasRole('vendor')) {
            return Auth::id();
        }

        return null;
    }

    /**
     * Check if vendor context is set
     */
    public static function hasContext(): bool
    {
        return static::$vendorId !== null ||
               (Auth::check() && Auth::user()->hasRole('vendor'));
    }

    /**
     * Clear the vendor context
     */
    public static function clear(): void
    {
        static::$vendorId = null;
        static::$enforced = false;
    }

    /**
     * Mark context as enforced
     */
    public static function enforce(): void
    {
        static::$enforced = true;
    }

    /**
     * Check if context is enforced
     */
    public static function isEnforced(): bool
    {
        return static::$enforced;
    }

    /**
     * Check if current user is admin (bypasses vendor filtering)
     */
    public static function isAdmin(): bool
    {
        return Auth::check() && Auth::user()->hasRole('admin');
    }

    /**
     * Check if current user is a vendor
     */
    public static function isVendor(): bool
    {
        return Auth::check() && Auth::user()->hasRole('vendor');
    }

    /**
     * Get the authenticated user
     */
    public static function getUser()
    {
        return Auth::user();
    }
}
