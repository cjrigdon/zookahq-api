<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleEnabled
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $tenant = tenant();

        if (! $tenant || ! $tenant->hasModule($module)) {
            abort(403, "Module [{$module}] is not enabled for this tenant.");
        }

        return $next($request);
    }
}
