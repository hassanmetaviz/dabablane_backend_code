<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FrontMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!Auth::check() || !Auth::user()->hasRole('user')) {
            return response()->json([
                'status' => false,
                'code' => 403,
                'message' => 'Unauthorized access.',
            ], 403);
        }

        return $next($request);
    }
}