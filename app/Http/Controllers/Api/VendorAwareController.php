<?php

namespace App\Http\Controllers\Api;

use App\Services\VendorContext;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;

abstract class VendorAwareController extends BaseController
{
    /**
     * Get the current vendor ID from context
     */
    protected function getVendorId(): ?int
    {
        return VendorContext::getVendorId();
    }

    /**
     * Check if current user is admin
     */
    protected function isAdmin(): bool
    {
        return VendorContext::isAdmin();
    }

    /**
     * Check if current user is a vendor
     */
    protected function isVendor(): bool
    {
        return VendorContext::isVendor();
    }

    /**
     * Check if current user has vendor context
     */
    protected function hasVendorContext(): bool
    {
        return VendorContext::hasContext();
    }

    /**
     * Authorize vendor access to a model
     */
    protected function authorizeVendorAccess(Model $model, string $ability = 'view'): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if (!$this->hasVendorContext()) {
            return false;
        }

        // Check if model has vendor_id
        if (isset($model->vendor_id)) {
            return $model->vendor_id === $this->getVendorId();
        }

        return false;
    }

    /**
     * Return unauthorized response for vendor operations
     */
    protected function unauthorizedVendor(string $message = null): JsonResponse
    {
        return $this->error(
            $message ?? 'Unauthorized: You can only access your own resources',
            [],
            403
        );
    }

    /**
     * Log a vendor data access event
     */
    protected function logAccess(string $action, array $context = []): void
    {
        AuditLogger::log("vendor.{$action}", array_merge([
            'vendor_id' => $this->getVendorId(),
            'is_admin' => $this->isAdmin(),
        ], $context));
    }

    /**
     * Apply vendor filtering to query (backward compatible with manual filtering)
     */
    protected function applyVendorFilter($query)
    {
        if (!$this->isAdmin() && $this->hasVendorContext()) {
            $query->where('vendor_id', $this->getVendorId());
        }
        return $query;
    }

    /**
     * Check if model is owned by current vendor
     */
    protected function isOwnedByCurrentVendor(Model $model): bool
    {
        if (!isset($model->vendor_id)) {
            return false;
        }

        return $model->vendor_id === $this->getVendorId();
    }

    /**
     * Check if model is a platform resource (no vendor)
     */
    protected function isPlatformResource(Model $model): bool
    {
        return !isset($model->vendor_id) || $model->vendor_id === null;
    }
}
