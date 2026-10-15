<?php

namespace App\Http\Middleware;

use App\Services\FeatureAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckFeatureAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(app(FeatureAccessService::class)->allowsRoute($request->user(), $request->route()?->getName()), 403,
            'Anda tidak memiliki hak akses ke fitur ini.');

        return $next($request);
    }
}
