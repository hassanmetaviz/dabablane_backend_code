<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\VendorContext;
use Symfony\Component\HttpFoundation\Response;

class VendorMiddleware
{
    /**
     * Handle an incoming request - ensures user is a vendor or admin.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            return response()->json([
                'status' => false,
                'code' => 401,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $user = Auth::user();

        // Allow admin or vendor roles
        if (!$user->hasAnyRole(['admin', 'vendor'])) {
            return response()->json([
                'status' => false,
                'code' => 403,
                'message' => 'Unauthorized: Vendor access required.',
            ], 403);
        }

        // Set vendor context for vendors
        if ($user->hasRole('vendor')) {
            VendorContext::set($user->id);
            VendorContext::enforce();
        }

        return $next($request);
    }
}
