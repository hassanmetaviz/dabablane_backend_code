<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\VendorContext;
use App\Services\AuditLogger;
use Symfony\Component\HttpFoundation\Response;

class EnforceVendorContext
{
    /**
     * Handle an incoming request.
     *
     * @param string $mode 'enforce' (default) or 'strict'
     */
    public function handle(Request $request, Closure $next, string $mode = 'enforce'): Response
    {
        // Check if middleware enforcement is enabled
        if (!config('multitenancy.enforce_middleware', true)) {
            return $next($request);
        }

        // Clear any previous context
        VendorContext::clear();

        if (!Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();

        // Admin users can optionally impersonate a vendor
        if ($user->hasRole('admin')) {
            $impersonateVendorId = $request->header('X-Vendor-Id');
            if ($impersonateVendorId && is_numeric($impersonateVendorId)) {
                VendorContext::set((int) $impersonateVendorId);
                AuditLogger::logImpersonation($user->id, (int) $impersonateVendorId);
            }
            return $next($request);
        }

        // Vendor users: enforce context
        if ($user->hasRole('vendor')) {
            VendorContext::set($user->id);
            VendorContext::enforce();

            // Merge vendor_id into request for easy access
            $request->merge(['vendor_id' => $user->id]);

            // Log data access for audit trail
            if (config('multitenancy.audit_logging_enabled', true)) {
                AuditLogger::log('vendor.request', [
                    'vendor_id' => $user->id,
                    'method' => $request->method(),
                    'path' => $request->path(),
                    'ip' => $request->ip()
                ]);
            }

            return $next($request);
        }

        // For strict mode, reject requests without valid vendor context
        if ($mode === 'strict' && !VendorContext::hasContext()) {
            return response()->json([
                'status' => false,
                'code' => 403,
                'message' => 'Vendor context required for this operation.'
            ], 403);
        }

        return $next($request);
    }

    /**
     * Handle after the response is sent
     */
    public function terminate(Request $request, Response $response): void
    {
        // Clear context after request completes
        VendorContext::clear();
    }
}
