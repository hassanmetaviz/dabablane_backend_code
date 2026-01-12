<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyFrontendRequest
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if the request has a valid origin
        $origin = $request->headers->get('origin');
        $allowedOrigins = config('cors.allowed_origins', []);
        
        // Check for API token
        $apiToken = $request->header('X-Auth-Token');
        $validToken = config('app.frontend_token');

        // No token in config, skip this check
        if (!$validToken) {
            return $next($request);
        }

        // Origin is from allowed list and token is valid
        if (in_array($origin, $allowedOrigins) && $apiToken === $validToken) {
            return $next($request);
        }

        // If request comes through a proxy (like Cloudflare)
        $referer = $request->header('referer');
        if ($referer) {
            foreach ($allowedOrigins as $allowed) {
                if (strpos($referer, $allowed) === 0) {
                    if ($apiToken === $validToken) {
                        return $next($request);
                    }
                }
            }
        }

        return response()->json([
            'status' => false,
            'code' => 403,
            'message' => 'Unauthorized request origin.',
        ], 403);
    }
} 