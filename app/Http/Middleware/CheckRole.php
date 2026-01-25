<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        // Admin (Super Admin) always allowed
        if (auth()->user()->role === 'admin') {
            return $next($request);
        }

        if (in_array(auth()->user()->role, $roles)) {
            return $next($request);
        }

        abort(403, 'Unauthorized - Invalid role');
    }
}
