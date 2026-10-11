<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InitializeTenancyBySlug
{
    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->header('X-Tenant');

        if (! $slug) {
            $host = $request->getHost();
            $central = config('tenancy.central_domains', []);

            if (! in_array($host, $central, true)) {
                $parts = explode('.', $host);
                if (count($parts) >= 2) {
                    $slug = $parts[0];
                }
            }
        }

        if (! $slug || in_array($slug, ['www', 'admin', 'api', 'localhost'], true)) {
            abort(404, 'Tenant not specified.');
        }

        $tenant = Tenant::query()->where('slug', $slug)->where('status', 'active')->first();

        if (! $tenant) {
            abort(404, 'Tenant not found.');
        }

        if (! tenancy()->initialized) {
            tenancy()->initialize($tenant);
        }

        return $next($request);
    }
}
