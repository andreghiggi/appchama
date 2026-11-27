<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\TenantDomain;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantScope
{
    public function handle(Request $request, Closure $next): Response
    {
        // Cada marca so aceita os proprios cadastros: a marca pedida pelo app tem de ser a do endereco.
        $pedidas = array_values(array_filter(
            [$request->header('X-Tenant-Slug'), $request->input('tenant_slug')],
            fn ($s) => is_string($s) && $s !== '',
        ));

        // Endereço da marca (api.<marca>): a marca vem do endereço e nada a sobrescreve.
        if (app()->bound('tenantFromHost')) {
            $user = $request->user();
            if ($user && $user->tenant_id && $user->tenant_id !== app('currentTenantId')) {
                abort(403, 'Este usuário não pertence a esta marca.');
            }

            // o cabecalho e ignorado aqui (o endereco manda); ja o tenant_slug do corpo e usado nos cadastros
            $doCorpo = $request->input('tenant_slug');
            if (is_string($doCorpo) && $doCorpo !== '') {
                $doEndereco = Tenant::query()->whereKey(app('currentTenantId'))->value('slug');
                abort_if($doCorpo !== $doEndereco, 422, 'Este aplicativo não pertence a este endereço.');
            }

            return $next($request);
        }

        // Endereco da plataforma: nao cadastra nem entra em marca que tem endereco proprio.
        foreach ($pedidas as $slug) {
            $temEnderecoProprio = TenantDomain::query()->where('type', 'api')
                ->whereIn('tenant_id', Tenant::query()->where('slug', $slug)->select('id'))->exists();
            abort_if($temEnderecoProprio, 422, 'Este aplicativo não pertence a este endereço.');
        }

        $tenantSlug = $request->header('X-Tenant-Slug')
            ?? $request->input('tenant_slug')
            ?? config('app.default_tenant_slug');

        if ($request->user()?->tenant_id) {
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
