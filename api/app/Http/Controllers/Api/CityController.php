<?php

namespace App\Http\Controllers\Api;

use App\Domains\Maps\Services\ServiceAreaService;
use App\Http\Controllers\Controller;
use App\Models\City;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CityController extends Controller
{
    public function __construct(
        private ServiceAreaService $serviceArea,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()?->tenant_id ?? app('currentTenantId');
        // ?for=driver: cidades abertas ao cadastro de motorista (independe de corridas); senao, cidades com corridas.
        $forDriver = $request->query('for') === 'driver';

        $cities = City::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('active', true)
            ->when(
                $forDriver,
                fn ($q) => $q->where('driver_signup_enabled', true),
                fn ($q) => $q->where('rides_enabled', true),
            )
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'state',
                'active',
                'base_fare',
                'price_per_km',
                'price_per_min',
                'min_fare',
                'departure_address',
                'departure_lat',
                'departure_lng',
                'rides_enabled',
                'driver_signup_enabled',
            ])
            ->filter(fn (City $city) => $forDriver ? $city->acceptsDriverSignup() : $city->acceptsRides())
            ->values();

        return response()->json($cities);
    }

    public function nearby(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
        ]);

        $tenantId = $request->user()?->tenant_id ?? app('currentTenantId');
        $lat = (float) $data['lat'];
        $lng = (float) $data['lng'];
        $maxRadiusKm = $this->serviceArea->maxRadiusKm();

        $matches = $this->serviceArea->listCitiesWithinRadius($lat, $lng, $tenantId);

        return response()->json([
            'max_radius_km' => $maxRadiusKm,
            'cities' => collect($matches)->map(fn (array $row) => [
                'id' => $row['city']->id,
                'name' => $row['city']->name,
                'state' => $row['city']->state,
                'distance_km' => $row['distance_km'],
                'within_service_area' => $row['within_service_area'],
                'departure_address' => $row['city']->departure_address,
                'departure_lat' => $row['city']->departure_lat,
                'departure_lng' => $row['city']->departure_lng,
                'base_fare' => $row['city']->base_fare,
                'price_per_km' => $row['city']->price_per_km,
                'price_per_min' => $row['city']->price_per_min,
                'min_fare' => $row['city']->min_fare,
                'rides_enabled' => $row['city']->rides_enabled,
            ])->values(),
        ]);
    }

    public function nearest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
            'city_id' => 'nullable|uuid|exists:cities,id',
            'select' => 'nullable|boolean',
        ]);

        $tenantId = $request->user()?->tenant_id ?? app('currentTenantId');
        $lat = (float) $data['lat'];
        $lng = (float) $data['lng'];
        $maxRadiusKm = $this->serviceArea->maxRadiusKm();

        if (! empty($data['city_id'])) {
            $city = City::query()->findOrFail($data['city_id']);
            $selectMode = $request->boolean('select');
            $center = $this->serviceArea->resolveDepartureCenter($city);

            if ($selectMode && $center) {
                $lat = $center['latitude'];
                $lng = $center['longitude'];
            }

            $resolved = $this->serviceArea->resolveSelectedCity($city, $lat, $lng);

            if ($selectMode) {
                $resolved['within_service_area'] = true;
                $resolved['distance_km'] = 0;
            }

            return response()->json([
                'city' => $city->only([
                    'id',
                    'name',
                    'state',
                    'base_fare',
                    'price_per_km',
                    'price_per_min',
                    'min_fare',
                    'departure_address',
                    'departure_lat',
                    'departure_lng',
                    'rides_enabled',
                ]),
                'distance_km' => $resolved['distance_km'],
                'max_radius_km' => $maxRadiusKm,
                'within_service_area' => $resolved['within_service_area'],
                'departure_center' => [
                    'latitude' => $resolved['center']['latitude'],
                    'longitude' => $resolved['center']['longitude'],
                    'label' => $resolved['center']['label'],
                ],
            ]);
        }

        $resolved = $this->serviceArea->resolveCityOrFail($lat, $lng, $tenantId);
        $city = $resolved['city'];

        return response()->json([
            'city' => $city->only([
                'id',
                'name',
                'state',
                'base_fare',
                'price_per_km',
                'price_per_min',
                'min_fare',
                'departure_address',
                'departure_lat',
                'departure_lng',
                'rides_enabled',
            ]),
            'distance_km' => $resolved['distance_km'],
            'max_radius_km' => $maxRadiusKm,
            'within_service_area' => true,
            'departure_center' => [
                'latitude' => $resolved['center']['latitude'],
                'longitude' => $resolved['center']['longitude'],
                'label' => $resolved['center']['label'],
            ],
        ]);
    }
}
