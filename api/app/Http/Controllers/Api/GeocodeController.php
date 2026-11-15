<?php

namespace App\Http\Controllers\Api;

use App\Domains\Maps\Services\GooglePlacesService;
use App\Domains\Maps\Services\MapboxService;
use App\Domains\Maps\Services\ServiceAreaService;
use App\Http\Controllers\Controller;
use App\Models\City;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GeocodeController extends Controller
{
    public function __construct(
        private MapboxService $mapbox,
        private GooglePlacesService $places,
        private ServiceAreaService $serviceArea,
    ) {}

    public function search(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => 'required|string|min:3|max:120',
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
            'city_id' => 'nullable|uuid',
        ]);

        if (! $this->mapbox->isConfigured()) {
            return response()->json([
                'message' => 'Geocoding indisponível. Configure MAPBOX_ACCESS_TOKEN.',
                'results' => [],
            ], 503);
        }

        $tenantId = $request->user()?->tenant_id ?? app('currentTenantId');
        $originLat = (float) $data['lat'];
        $originLng = (float) $data['lng'];
        $maxRadiusKm = $this->serviceArea->maxRadiusKm();

        if (! empty($data['city_id'])) {
            $city = City::query()->findOrFail($data['city_id']);
            $center = $this->serviceArea->resolveDepartureCenter($city, null, $originLat, $originLng);

            if (! $center) {
                return response()->json([
                    'message' => "Configure o endereço de partida de {$city->name} no admin.",
                    'results' => [],
                ], 422);
            }
        } else {
            $resolved = $this->serviceArea->resolveCityOrFail($originLat, $originLng, $tenantId);
            $city = $resolved['city'];
            $center = $resolved['center'];
        }

        $query = $this->contextualQuery($data['q'], $city);

        $search = $this->mapbox->searchWithMeta(
            $query,
            $center['latitude'],
            $center['longitude'],
            $maxRadiusKm,
            useBbox: ! empty($data['city_id']),
        );

        if ($search['results'] === [] && $city->name) {
            $retry = $this->mapbox->searchWithMeta(
                $query,
                $center['latitude'],
                $center['longitude'],
                $maxRadiusKm,
                useBbox: false,
            );

            if ($retry['results'] !== []) {
                $search = $retry;
            } elseif ($search['mapbox_count'] > 0 || $retry['mapbox_count'] > 0) {
                $fallback = $this->mapbox->searchWithMeta(
                    $query,
                    $center['latitude'],
                    $center['longitude'],
                    null,
                    useBbox: false,
                );

                $search = $this->filterResultsForCity($fallback, $city, $center, $maxRadiusKm * 1.6);
            }
        }

        $results = $this->withPlaces($data['q'], $search['results'], $center, $maxRadiusKm);

        return response()->json([
            'results' => $results,
            'filtered_count' => $search['filtered_count'],
            'mapbox_count' => $search['mapbox_count'],
            'max_radius_km' => $maxRadiusKm,
            'city_id' => $city->id,
            'city_name' => $city->name,
            'departure_center' => [
                'latitude' => $center['latitude'],
                'longitude' => $center['longitude'],
                'label' => $center['label'],
            ],
        ]);
    }

    /**
     * Junta os LOCAIS do Google (hospital, supermercado, loja...) aos enderecos do Mapbox.
     * Busca que parece endereco (rua, numero) mostra enderecos primeiro; as demais, locais primeiro.
     *
     * @param  list<array{latitude: float, longitude: float, label: string}>  $addresses
     * @param  array{latitude: float, longitude: float, label: ?string}  $center
     * @return list<array{latitude: float, longitude: float, label: string}>
     */
    private function withPlaces(string $query, array $addresses, array $center, float $maxRadiusKm): array
    {
        if (! $this->places->isConfigured()) {
            return $addresses;
        }

        $places = collect($this->places->search($query, $center['latitude'], $center['longitude'], $maxRadiusKm))
            ->filter(fn (array $p) => $this->serviceArea->distanceKm(
                $center['latitude'],
                $center['longitude'],
                $p['latitude'],
                $p['longitude'],
            ) <= $maxRadiusKm)
            ->values()
            ->all();

        if ($places === []) {
            return $addresses;
        }

        $looksLikeAddress = preg_match('/\d|^\s*(rua|r\.|avenida|av\.?|travessa|estrada|rodovia|alameda|praca|praça|beco)/iu', $query) === 1;
        $merged = $looksLikeAddress ? array_merge($addresses, $places) : array_merge($places, $addresses);

        $seen = [];
        $unique = [];
        foreach ($merged as $item) {
            $k = round($item['latitude'], 4).','.round($item['longitude'], 4);
            if (isset($seen[$k])) {
                continue;
            }
            $seen[$k] = true;
            $unique[] = $item;
        }

        return array_slice($unique, 0, 8);
    }

    private function contextualQuery(string $query, City $city): string
    {
        $contextQuery = trim($query);
        // Compara sem acento: "guapore" e "Guaporé" sao a mesma cidade.
        $cityNeedle = Str::ascii(mb_strtolower($city->name));

        if ($cityNeedle !== '' && ! str_contains(Str::ascii(mb_strtolower($contextQuery)), $cityNeedle)) {
            $contextQuery .= ', '.$city->name;
            if ($city->state) {
                $contextQuery .= ', '.$city->state;
            }
        }

        return $contextQuery;
    }

    /**
     * @param  array{results: list<array{latitude: float, longitude: float, label: string}>, filtered_count: int, mapbox_count: int}  $search
     * @param  array{latitude: float, longitude: float, label: ?string}  $center
     * @return array{results: list<array{latitude: float, longitude: float, label: string}>, filtered_count: int, mapbox_count: int}
     */
    private function filterResultsForCity(array $search, City $city, array $center, float $maxRadiusKm): array
    {
        $cityNeedle = mb_strtolower($city->name);
        $stateNeedle = $city->state ? mb_strtolower($city->state) : null;

        $results = collect($search['results'])
            ->filter(function (array $result) use ($cityNeedle, $stateNeedle, $center, $maxRadiusKm) {
                $label = mb_strtolower($result['label']);

                if (! str_contains($label, $cityNeedle)) {
                    return false;
                }

                if ($stateNeedle && ! str_contains($label, $stateNeedle)) {
                    return false;
                }

                return $this->serviceArea->distanceKm(
                    $center['latitude'],
                    $center['longitude'],
                    $result['latitude'],
                    $result['longitude'],
                ) <= $maxRadiusKm;
            })
            ->take(8)
            ->values()
            ->all();

        return [
            'results' => $results,
            'filtered_count' => max(0, $search['mapbox_count'] - count($results)),
            'mapbox_count' => $search['mapbox_count'],
        ];
    }

    public function reverse(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
        ]);

        return response()->json($this->mapbox->reverseDetails((float) $data['lat'], (float) $data['lng']));
    }
}
