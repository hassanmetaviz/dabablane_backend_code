<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Multi-tenancy Configuration
    |--------------------------------------------------------------------------
    |
    | These settings control the multi-tenancy behavior of the application.
    | Use environment variables to enable/disable features for rollback safety.
    |
    */

    'enabled' => env('MULTITENANCY_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Global Scope
    |--------------------------------------------------------------------------
    |
    | When enabled, the VendorScope global scope will automatically filter
    | queries for vendor users to only show their own resources.
    | Admins always bypass this scope.
    |
    */
    'global_scope_enabled' => env('MULTITENANCY_GLOBAL_SCOPE', true),

    /*
    |--------------------------------------------------------------------------
    | Audit Logging
    |--------------------------------------------------------------------------
    |
    | When enabled, vendor data access will be logged to both file and database
    | for compliance and debugging purposes.
    |
    */
    'audit_logging_enabled' => env('MULTITENANCY_AUDIT_LOG', true),

    /*
    |--------------------------------------------------------------------------
    | Enforce Middleware
    |--------------------------------------------------------------------------
    |
    | When enabled, the EnforceVendorContext middleware will be active.
    | Disable this to fall back to the old SetVendorContext behavior.
    |
    */
    'enforce_middleware' => env('MULTITENANCY_ENFORCE_MIDDLEWARE', true),

    /*
    |--------------------------------------------------------------------------
    | Legacy Support
    |--------------------------------------------------------------------------
    |
    | When enabled, the system will also check commerce_name for backward
    | compatibility with old data that hasn't been migrated to vendor_id.
    |
    */
    'legacy_commerce_name_support' => env('MULTITENANCY_LEGACY_SUPPORT', true),
];
