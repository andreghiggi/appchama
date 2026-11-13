<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\TenantDomain;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Define a marca da requisição pelo ENDEREÇO (api.<marca>, adm.<marca>, site).
 * Endereços da plataforma não fixam marca: o administrador da plataforma vê todas (ou a marca
 * escolhida no filtro). Roda antes de qualquer consulta, então os escopos de marca já valem.
 */
class ResolveTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower($request->getHost());

        if (! in_array($host, config('tenancy.platform_hosts', []), true)) {
            try {
                $dominio = TenantDomain::query()->where('host', $host)->first();
            } catch (\Throwable) {
                $dominio = null; // tabela ainda nao existe (antes da migracao)
            }
            if ($dominio) {
                app()->instance('currentTenantId', $dominio->tenant_id);
                app()->instance('tenantFromHost', true);
                $request->attributes->set('tenant_host_type', $dominio->type);
            }
        }

        // Filtro de marca do administrador da plataforma (painel).
        if (! app()->bound('currentTenantId') && $request->hasSession()) {
            try {
                $slug = $request->session()->get('tenant_filter');
                if ($slug && $request->user()?->is_platform_admin) {
                    $id = Tenant::query()->where('slug', $slug)->value('id');
                    if ($id) {
                        app()->instance('currentTenantId', $id);
                    }
                }
            } catch (\Throwable) {
                // sem sessao/banco pronto: segue sem filtro
            }
        }

        $this->aplicarIdentidadeVisual();

        return $next($request);
    }

    /** Cor da marca no painel (nome e logo são resolvidos no próprio painel). */
    private function aplicarIdentidadeVisual(): void
    {
        if (! app()->bound('currentTenantId') || ! class_exists(\Filament\Support\Facades\FilamentColor::class)) {
            return;
        }
        try {
            $cor = Tenant::query()->whereKey(app('currentTenantId'))->value('primary_color');
            if ($cor && preg_match('/^#[0-9a-fA-F]{6}$/', $cor)) {
                \Filament\Support\Facades\FilamentColor::register(['primary' => \Filament\Support\Colors\Color::hex($cor)]);
            }
        } catch (\Throwable) {
            // identidade visual nunca pode derrubar a requisição
        }
    }
}
