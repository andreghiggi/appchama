<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantScope
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenantSlug = $request->header('X-Tenant-Slug')
            ?? $request->input('tenant_slug')
            ?? config('app.default_tenant_slug');

        if ($request->user()) {
            app()->instance('currentTenantId', $request->user()->tenant_id);
            return $next($request);
        }

        if ($tenantSlug) {
            $tenant = Tenant::query()->where('slug', $tenantSlug)->first();
            if ($tenant) {
                app()->instance('currentTenantId', $tenant->id);
            }
        }

        return $next($request);
    }
}
