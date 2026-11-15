<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Informa ao app aberto qual e a versao mais nova na loja, para ele avisar o usuario. */
class AppVersionController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $data = $request->validate(['app' => 'required|in:passenger,driver']);

        $tenantId = app()->bound('currentTenantId') ? app('currentTenantId') : null;
        $slug = $tenantId ? Tenant::query()->whereKey($tenantId)->value('slug') : null;
        $cfg = config("app_versions.tenants.{$slug}.{$data['app']}", []);

        return response()->json([
            'latest' => (int) ($cfg['latest'] ?? 0),
            'minimum' => (int) ($cfg['minimum'] ?? 0),
        ])->header('Cache-Control', 'no-store');
    }
}
