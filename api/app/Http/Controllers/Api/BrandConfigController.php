<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;

class BrandConfigController extends Controller
{
    public function show(): JsonResponse
    {
        $tenantId = app()->bound("currentTenantId") ? app("currentTenantId") : null;
        $tenant = $tenantId ? Tenant::query()->find($tenantId) : null;

        if (! $tenant || $tenant->status !== "active") {
            return response()->json(["message" => "Marca nao encontrada."], 404);
        }

        return response()->json(["data" => $tenant->brandConfig()])
            ->header("Cache-Control", "public, max-age=300")
            // a resposta muda conforme a marca (endereco ou cabecalho): caches nao podem misturar marcas
            ->header("Vary", "X-Tenant-Slug, Origin");
    }
}
