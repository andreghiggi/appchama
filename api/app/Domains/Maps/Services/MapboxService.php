<?php

namespace App\Domains\Maps\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MapboxService
{
    public function isConfigured(): bool
    {
        return (bool) config('services.mapbox.token');
    }

    /**
     * @return array{distance_km: float, duration_min: int, polyline: ?string, coordinates: array<int, array{latitude: float, longitude: float}>}
     */
    public function directions(float $originLat, float $originLng, float $destLat, float $destLng): array
    {
        $token = config('services.mapbox.token');

        if (! $token) {
            return $this->fallbackRoute($originLat, $originLng, $destLat, $destLng);
        }

        try {
            $url = "https://api.mapbox.com/directions/v5/mapbox/driving/{$originLng},{$originLat};{$destLng},{$destLat}";
            $response = Http::get($url, [
                'access_token' => $token,
                'geometries' => 'polyline6',
                'overview' => 'full',
            ]);

            if ($response->successful()) {
                $route = $response->json('routes.0');

                if ($route) {
                    $polyline = $route['geometry'] ?? null;
                    $coordinates = $polyline ? $this->decodePolyline6($polyline) : [];

                    if (count($coordinates) < 2) {
                        Log::warning('Mapbox directions returned empty polyline; using straight fallback');

                        return $this->fallbackRoute($originLat, $originLng, $destLat, $destLng);
                    }

                return $this->normalizeDirectionsResult(
                    round(($route['distance'] ?? 0) / 1000, 2),
                    (int) ceil(($route['duration'] ?? 0) / 60),
                    $polyline,
                    $coordinates,
                    $originLat,
                    $originLng,
                    $destLat,
                    $destLng,
                );
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Mapbox directions failed: '.$e->getMessage());
        }

        return $this->fallbackRoute($originLat, $originLng, $destLat, $destLng);
    }

    /**
     * @return array{results: list<array{latitude: float, longitude: float, label: string}>, filtered_count: int, mapbox_count: int}
     */
    public function searchWithMeta(
        string $query,
        ?float $lat = null,
        ?float $lng = null,
        ?float $maxRadiusKm = null,
        bool $useBbox = false,
    ): array {
        $token = config('services.mapbox.token');
        $q = trim($query);

        if (! $token || strlen($q) < 3) {
            return ['results' => [], 'filtered_count' => 0, 'mapbox_count' => 0];
        }

        try {
            $encoded = urlencode($q);
            $params = [
                'access_token' => $token,
                'autocomplete' => 'true',
                'limit' => 15,
                'country' => 'BR',
                'language' => 'pt',
                'types' => 'poi,address,place,locality,neighborhood',
            ];

            if ($lat !== null && $lng !== null) {
                $params['proximity'] = "{$lng},{$lat}";

                if ($useBbox) {
                    $radius = $maxRadiusKm ?? (float) config('app.geocode_max_radius_km', 50);
                    $params['bbox'] = $this->bboxForRadius($lng, $lat, $radius * 1.1);
                }
            }

            $response = Http::get("https://api.mapbox.com/geocoding/v5/mapbox.places/{$encoded}.json", $params);

            if (! $response->successful()) {
                return ['results' => [], 'filtered_count' => 0, 'mapbox_count' => 0];
            }

            $radius = $maxRadiusKm ?? (float) config('app.geocode_max_radius_km', 50);
            $filterCenter = ($lat !== null && $lng !== null) ? [$lat, $lng] : null;
            $features = collect($response->json('features', []));
            $mapboxCount = $features->count();
            // Prioriza estabelecimentos/endereço; o Mapbox às vezes retorna cidade/bairro primeiro.
            $features = $features->sortByDesc(fn (array $feature): int => $this->searchRelevanceScore($feature, $q))->values();
            $mapped = $features
                ->map(fn (array $feature) => [
                    'latitude' => (float) $feature['center'][1],
                    'longitude' => (float) $feature['center'][0],
                    'label' => $feature['place_name'] ?? $q,
                ])
                ->all();
            // O índice Mapbox pode não conter POIs locais. Photon/O​​SM entra somente como fallback.
            $queryHasNumber = preg_match('/\d/', $q) === 1;
            $hasStrongMatch = $features->contains(function (array $feature) use ($q, $queryHasNumber): bool {
                $id = mb_strtolower((string) ($feature['id'] ?? ''));
                $type = mb_strtolower((string) ($feature['properties']['feature_type'] ?? ''));
                $isPoi = str_starts_with($id, 'poi.') || $type === 'poi';
                return $this->searchRelevanceScore($feature, $q) >= 85 && ($isPoi || $queryHasNumber);
            });
            if (! $hasStrongMatch) {
                $mapped = array_values(array_unique(array_merge($this->searchPhoton($q, $lat, $lng), $mapped), SORT_REGULAR));
            }
            $outsideRadius = 0;
            $filtered = collect($mapped)
                ->filter(function (array $result) use ($filterCenter, $radius, $maxRadiusKm, &$outsideRadius) {
                    if (! $filterCenter || $maxRadiusKm === null) {
                        return true;
                    }

                    $within = $this->haversine(
                        $filterCenter[0],
                        $filterCenter[1],
                        $result['latitude'],
                        $result['longitude'],
                    ) <= $radius;

                    if (! $within) {
                        $outsideRadius++;
                    }

                    return $within;
                })
                ->take(8)
                ->values()
                ->all();

            return [
                'results' => $filtered,
                'filtered_count' => $outsideRadius,
                'mapbox_count' => $mapboxCount,
            ];
        } catch (\Throwable $e) {
            Log::warning('Mapbox geocode search failed: '.$e->getMessage());

            return ['results' => [], 'filtered_count' => 0, 'mapbox_count' => 0];
        }
    }

    private function searchPhoton(string $query, ?float $lat, ?float $lng): array
    {
        try {
            $params = ['q' => $query, 'limit' => 8];
            if ($lat !== null && $lng !== null) {
                $params['lat'] = $lat;
                $params['lon'] = $lng;
            }
            $response = Http::timeout(5)->withHeaders(['User-Agent' => 'AppChama/1.0 (geocoding)'])
                ->get('https://photon.komoot.io/api/', $params);
            if (! $response->successful()) {
                return [];
            }
            return collect($response->json('features', []))->map(function (array $feature): ?array {
                $coordinates = $feature['geometry']['coordinates'] ?? null;
                if (! is_array($coordinates) || count($coordinates) < 2) {
                    return null;
                }
                $props = $feature['properties'] ?? [];
                $name = trim((string) ($props['name'] ?? ''));
                $street = trim(implode(' ', array_filter([(string) ($props['street'] ?? ''), (string) ($props['housenumber'] ?? '')])));
                $locality = trim(implode(', ', array_filter([(string) ($props['district'] ?? ''), (string) ($props['city'] ?? ''), (string) ($props['state'] ?? ''), (string) ($props['postcode'] ?? '')])));
                $label = trim(implode(', ', array_filter([$name, $street, $locality])));
                if ($label === '') {
                    return null;
                }
                return ['latitude' => (float) $coordinates[1], 'longitude' => (float) $coordinates[0], 'label' => $label];
            })->filter()->values()->all();
        } catch (\Throwable $e) {
            Log::notice('Photon geocode fallback failed: '.$e->getMessage());
            return [];
        }
    }

    private function searchRelevanceScore(array $feature, string $query): int
    {
        $label = mb_strtolower((string) ($feature['place_name'] ?? ''));
        $normalizedLabel = iconv('UTF-8', 'ASCII//TRANSLIT', $label) ?: $label;
        $normalizedQuery = mb_strtolower(trim($query));
        $normalizedQuery = iconv('UTF-8', 'ASCII//TRANSLIT', $normalizedQuery) ?: $normalizedQuery;
        $tokens = preg_split('/\s+/', preg_replace('/[^a-z0-9]+/i', ' ', $normalizedQuery) ?: '') ?: [];
        $tokens = array_values(array_filter($tokens, fn (string $token): bool => strlen($token) >= 3));
        $score = 0;
        $id = mb_strtolower((string) ($feature['id'] ?? ''));
        $featureType = mb_strtolower((string) ($feature['properties']['feature_type'] ?? ''));
        if (str_starts_with($id, 'poi.') || $featureType === 'poi') {
            $score += 100;
        } elseif (str_starts_with($id, 'address.') || $featureType === 'address') {
            $score += 85;
        } elseif (str_starts_with($id, 'place.') || str_starts_with($id, 'locality.')) {
            $score -= 30;
        }
        foreach ($tokens as $token) {
            if (str_contains($normalizedLabel, $token)) {
                $score += 12;
            }
        }
        if ($tokens !== [] && str_contains($normalizedLabel, implode(' ', $tokens))) {
            $score += 35;
        }
        preg_match_all('/\d+/', $normalizedQuery, $queryNumbers);
        foreach ($queryNumbers[0] as $number) {
            if (str_contains($normalizedLabel, $number)) {
                $score += 30;
            }
        }
        return $score;
    }

    /**
     * @return list<array{latitude: float, longitude: float, label: string}>
     */
    public function search(
        string $query,
        ?float $lat = null,
        ?float $lng = null,
        ?float $maxRadiusKm = null,
    ): array {
        return $this->searchWithMeta($query, $lat, $lng, $maxRadiusKm)['results'];
    }

    /** @return string minLng,minLat,maxLng,maxLat */
    private function bboxForRadius(float $lng, float $lat, float $radiusKm): string
    {
        $latDelta = $radiusKm / 111.0;
        $lngDelta = $radiusKm / max(cos(deg2rad($lat)) * 111.0, 0.01);

        $minLng = $lng - $lngDelta;
        $maxLng = $lng + $lngDelta;
        $minLat = max(-90, $lat - $latDelta);
        $maxLat = min(90, $lat + $latDelta);

        return implode(',', [$minLng, $minLat, $maxLng, $maxLat]);
    }

    public function reverse(float $lat, float $lng): string
    {
        return $this->reverseDetails($lat, $lng)['label'];
    }

    /**
     * @return array{label: string, postal_code: ?string, city: ?string, state: ?string, address: ?string}
     */
    public function reverseDetails(float $lat, float $lng): array
    {
        $fallback = [
            'label' => 'Local de embarque',
            'postal_code' => null,
            'city' => null,
            'state' => null,
            'address' => null,
        ];

        $token = config('services.mapbox.token');

        if (! $token) {
            return $fallback;
        }

        try {
            $response = Http::get("https://api.mapbox.com/geocoding/v5/mapbox.places/{$lng},{$lat}.json", [
                'access_token' => $token,
                'language' => 'pt',
                'limit' => 1,
            ]);

            if (! $response->successful()) {
                return $fallback;
            }

            $feature = $response->json('features.0');

            if (! $feature) {
                return $fallback;
            }

            $place = $feature['place_name'] ?? 'Local de embarque';
            $context = collect($feature['context'] ?? []);

            $postalCode = preg_replace('/\D/', '', (string) (
                $context->first(fn ($item) => str_contains($item['id'] ?? '', 'postcode'))['text'] ?? ''
            )) ?: null;

            $city = $context->first(fn ($item) => str_contains($item['id'] ?? '', 'place'))['text']
                ?? $context->first(fn ($item) => str_contains($item['id'] ?? '', 'locality'))['text']
                ?? null;

            $state = $context->first(fn ($item) => str_contains($item['id'] ?? '', 'region'))['short_code'] ?? null;
            if ($state && str_contains($state, '-')) {
                $state = strtoupper(substr($state, strrpos($state, '-') + 1));
            }

            $address = $feature['text'] ?? explode(',', $place)[0] ?? null;

            return [
                'label' => implode(', ', array_slice(explode(',', $place), 0, 3)),
                'postal_code' => $postalCode,
                'city' => $city,
                'state' => $state,
                'address' => $address,
            ];
        } catch (\Throwable $e) {
            Log::warning('Mapbox reverse geocode failed: '.$e->getMessage());

            return $fallback;
        }
    }

    /**
     * @return array{distance_km: float, duration_min: int, polyline: null, coordinates: array<int, array{latitude: float, longitude: float}>}
     */
    private function fallbackRoute(float $originLat, float $originLng, float $destLat, float $destLng): array
    {
        $distanceKm = $this->haversine($originLat, $originLng, $destLat, $destLng);
        $steps = 24;
        $coordinates = [];

        for ($i = 0; $i <= $steps; $i++) {
            $t = $i / $steps;
            $coordinates[] = [
                'latitude' => $originLat + ($destLat - $originLat) * $t,
                'longitude' => $originLng + ($destLng - $originLng) * $t,
            ];
        }

        return $this->normalizeDirectionsResult(
            round($distanceKm, 2),
            (int) ceil(max($distanceKm, 0.05) / 0.5),
            null,
            $coordinates,
            $originLat,
            $originLng,
            $destLat,
            $destLng,
        );
    }

    /**
     * @param  array<int, array{latitude: float, longitude: float}>  $coordinates
     * @return array{distance_km: float, duration_min: int, polyline: ?string, coordinates: array<int, array{latitude: float, longitude: float}>}
     */
    private function normalizeDirectionsResult(
        float $distanceKm,
        int $durationMin,
        ?string $polyline,
        array $coordinates,
        float $originLat,
        float $originLng,
        float $destLat,
        float $destLng,
    ): array {
        $valid = collect($coordinates)
            ->filter(function ($point) {
                if (! is_array($point)) {
                    return false;
                }

                $lat = (float) ($point['latitude'] ?? $point['lat'] ?? 0);
                $lng = (float) ($point['longitude'] ?? $point['lng'] ?? 0);

                return ! ($lat === 0.0 && $lng === 0.0) && abs($lat) <= 90 && abs($lng) <= 180;
            })
            ->map(fn (array $point) => [
                'latitude' => (float) ($point['latitude'] ?? $point['lat']),
                'longitude' => (float) ($point['longitude'] ?? $point['lng']),
            ])
            ->values()
            ->all();

        if (count($valid) < 2) {
            $valid = [
                ['latitude' => $originLat, 'longitude' => $originLng],
                ['latitude' => $destLat, 'longitude' => $destLng],
            ];
        }

        return [
            'distance_km' => $distanceKm,
            'duration_min' => $durationMin,
            'polyline' => $polyline,
            'coordinates' => $valid,
        ];
    }

    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * @return array<int, array{latitude: float, longitude: float}>
     */
    private function decodePolyline6(string $encoded): array
    {
        $coordinates = [];
        $index = 0;
        $lat = 0;
        $lng = 0;
        $length = strlen($encoded);

        while ($index < $length) {
            [$index, $latChange] = $this->decodePolylineValue($encoded, $index);
            [$index, $lngChange] = $this->decodePolylineValue($encoded, $index);

            $lat += $latChange;
            $lng += $lngChange;

            $coordinates[] = [
                'latitude' => $lat / 1e6,
                'longitude' => $lng / 1e6,
            ];
        }

        return $coordinates;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function decodePolylineValue(string $encoded, int $index): array
    {
        $result = 0;
        $shift = 0;
        $byte = 0;

        do {
            $byte = ord($encoded[$index]) - 63;
            $index++;
            $result |= ($byte & 0x1F) << $shift;
            $shift += 5;
        } while ($byte >= 0x20);

        $delta = ($result & 1) ? ~($result >> 1) : ($result >> 1);

        return [$index, $delta];
    }
}
