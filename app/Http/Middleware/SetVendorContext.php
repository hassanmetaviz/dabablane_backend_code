<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SetVendorContext
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Set vendor context in request for easy access
        // This is optional middleware - it doesn't enforce anything, just sets context
        if (Auth::check() && Auth::user()->hasRole('vendor')) {
            $request->merge(['vendor_id' => Auth::id()]);
        }

        return $next($request);
    }
}




