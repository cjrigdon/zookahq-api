<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Early tenancy bootstrap for API requests that include X-Tenant,
 * so Sanctum token lookup hits the tenant database.
 */
class EarlyInitializeTenancyFromHeader
{
    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->header('X-Tenant');

        if ($slug && ! in_array($slug, ['www', 'admin', 'api', 'localhost'], true)) {
            $tenant = Tenant::query()->where('slug', $slug)->where('status', 'active')->first();
            if ($tenant && ! tenancy()->initialized) {
                tenancy()->initialize($tenant);
            }
        }

        return $next($request);
    }
}
