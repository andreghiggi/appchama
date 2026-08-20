<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\City;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()?->tenant_id ?? app('currentTenantId');

        $cities = City::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('active', true)
            ->get(['id', 'name', 'state', 'base_fare', 'price_per_km', 'price_per_min', 'min_fare']);

        return response()->json($cities);
    }
}
