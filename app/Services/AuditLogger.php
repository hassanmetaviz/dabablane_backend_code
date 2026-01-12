<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\AuditLog;

class AuditLogger
{
    /**
     * Log an audit event
     */
    public static function log(string $event, array $context = []): void
    {
        if (!config('multitenancy.audit_logging_enabled', true)) {
            return;
        }

        $logEntry = [
            'event' => $event,
            'user_id' => Auth::id(),
            'user_email' => Auth::check() ? Auth::user()->email : null,
            'user_role' => Auth::check() ? implode(',', Auth::user()->getRoleNames()->toArray()) : null,
            'timestamp' => now()->toIso8601String(),
            'context' => $context,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ];

        // Log to file
        Log::channel('audit')->info($event, $logEntry);

        // Store in database for queryable audit trail
        try {
            AuditLog::create([
                'event' => $event,
                'user_id' => Auth::id(),
                'vendor_id' => $context['vendor_id'] ?? VendorContext::getVendorId(),
                'model_type' => $context['model_type'] ?? null,
                'model_id' => $context['model_id'] ?? null,
                'action' => $context['action'] ?? self::extractAction($event),
                'old_values' => $context['old_values'] ?? null,
                'new_values' => $context['new_values'] ?? null,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'metadata' => json_encode($context),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to write audit log to database', [
                'error' => $e->getMessage(),
                'event' => $event
            ]);
        }
    }

    /**
     * Log model changes
     */
    public static function logModelChange(string $model, int $modelId, string $action, ?array $oldValues = null, ?array $newValues = null): void
    {
        self::log("model.{$action}", [
            'model_type' => $model,
            'model_id' => $modelId,
            'action' => $action,
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);
    }

    /**
     * Log vendor data access
     */
    public static function logDataAccess(string $resource, ?int $resourceId = null, string $action = 'view'): void
    {
        self::log("vendor.data_access", [
            'resource' => $resource,
            'resource_id' => $resourceId,
            'action' => $action,
            'vendor_id' => VendorContext::getVendorId(),
        ]);
    }

    /**
     * Log admin impersonation
     */
    public static function logImpersonation(int $adminId, int $vendorId): void
    {
        self::log("admin.impersonate", [
            'admin_id' => $adminId,
            'impersonated_vendor_id' => $vendorId,
        ]);
    }

    /**
     * Extract action from event name
     */
    private static function extractAction(string $event): string
    {
        $parts = explode('.', $event);
        return end($parts);
    }
}
