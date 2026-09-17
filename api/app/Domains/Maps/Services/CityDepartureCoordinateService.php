<?php

namespace App\Domains\Maps\Services;

use App\Models\City;
use Illuminate\Validation\ValidationException;

class CityDepartureCoordinateService
{
    /** Distância máxima (km) entre coordenadas manuais e centro geocodificado antes de auto-corrigir. */
    private const AUTO_CORRECT_DISTANCE_KM = 20;

    /** @var array<string, array{lat: array{0: float, 1: float}, lng: array{0: float, 1: float}}> */
    private const STATE_BOUNDS = [
        'AC' => ['lat' => [-11.15, -7.05], 'lng' => [-73.99, -66.62]],
        'AL' => ['lat' => [-10.50, -8.81], 'lng' => [-38.24, -35.15]],
        'AM' => ['lat' => [-9.82, 2.25], 'lng' => [-73.80, -56.10]],
        'AP' => ['lat' => [-1.23, 4.44], 'lng' => [-54.88, -49.87]],
        'BA' => ['lat' => [-18.35, -8.53], 'lng' => [-46.62, -37.34]],
        'CE' => ['lat' => [-7.86, -2.78], 'lng' => [-41.42, -37.25]],
        'DF' => ['lat' => [-16.05, -15.50], 'lng' => [-48.28, -47.30]],
        'ES' => ['lat' => [-21.30, -17.89], 'lng' => [-41.88, -39.67]],
        'GO' => ['lat' => [-19.50, -12.39], 'lng' => [-53.25, -45.91]],
        'MA' => ['lat' => [-10.26, -1.04], 'lng' => [-48.76, -41.80]],
        'MG' => ['lat' => [-22.92, -14.23], 'lng' => [-51.05, -39.86]],
        'MS' => ['lat' => [-24.06, -17.17], 'lng' => [-58.17, -50.92]],
        'MT' => ['lat' => [-18.04, -7.35], 'lng' => [-61.63, -50.22]],
        'PA' => ['lat' => [-9.84, 2.59], 'lng' => [-58.90, -46.06]],
        'PB' => ['lat' => [-8.30, -6.02], 'lng' => [-38.77, -34.79]],
        'PE' => ['lat' => [-9.48, -7.24], 'lng' => [-41.36, -34.79]],
        'PI' => ['lat' => [-10.93, -4.96], 'lng' => [-45.99, -40.37]],
        'PR' => ['lat' => [-26.72, -22.52], 'lng' => [-54.62, -48.02]],
        'RJ' => ['lat' => [-23.37, -20.76], 'lng' => [-44.89, -40.96]],
        'RN' => ['lat' => [-6.98, -4.83], 'lng' => [-38.58, -34.97]],
        'RO' => ['lat' => [-13.70, -7.97], 'lng' => [-66.62, -59.77]],
        'RR' => ['lat' => [0.05, 5.27], 'lng' => [-64.82, -59.14]],
        'RS' => ['lat' => [-33.75, -27.08], 'lng' => [-57.65, -49.58]],
        'SC' => ['lat' => [-29.35, -25.96], 'lng' => [-53.84, -48.35]],
        'SE' => ['lat' => [-11.57, -9.51], 'lng' => [-38.24, -36.39]],
        'SP' => ['lat' => [-25.31, -19.78], 'lng' => [-53.11, -44.16]],
        'TO' => ['lat' => [-13.47, -5.17], 'lng' => [-50.73, -45.73]],
    ];

    public function __construct(
        private MapboxService $mapbox,
    ) {}

    /**
     * Normaliza e valida coordenadas de partida de uma cidade.
     *
     * @return array{
     *     lat: ?float,
     *     lng: ?float,
     *     corrected: bool,
     *     message: ?string,
     *     reference_label: ?string
     * }
     */
    public function normalize(
        mixed $lat,
        mixed $lng,
        string $cityName,
        string $state,
        bool $autoCorrect = true,
        bool $strict = false,
    ): array {
        $cityName = trim($cityName);
        $state = strtoupper(trim($state));

        $lat = $this->normalizeBrazilCoordinate(City::normalizeCoordinate($lat), isLatitude: true);
        $lng = $this->normalizeBrazilCoordinate(City::normalizeCoordinate($lng), isLatitude: false);

        if ($lat !== null && ($lat < -90 || $lat > 90)) {
            throw ValidationException::withMessages([
                'departure_lat' => 'Latitude deve estar entre -90 e 90.',
            ]);
        }

        if ($lng !== null && ($lng < -180 || $lng > 180)) {
            throw ValidationException::withMessages([
                'departure_lng' => 'Longitude deve estar entre -180 e 180.',
            ]);
        }

        if ($lat !== null && $lng !== null && $lat == 0.0 && $lng == 0.0) {
            $lat = null;
            $lng = null;
        }

        $reference = $this->geocodeCityCenter($cityName, $state);

        if ($reference === null) {
            $this->assertWithinStateBounds($lat, $lng, $state, $strict);

            return [
                'lat' => $lat,
                'lng' => $lng,
                'corrected' => false,
                'message' => null,
                'reference_label' => null,
            ];
        }

        if ($lat === null || $lng === null) {
            return [
                'lat' => $reference['latitude'],
                'lng' => $reference['longitude'],
                'corrected' => true,
                'message' => "Coordenadas preenchidas automaticamente para {$cityName} ({$state}).",
                'reference_label' => $reference['label'],
            ];
        }

        $distanceKm = $this->haversine($lat, $lng, $reference['latitude'], $reference['longitude']);

        if ($distanceKm <= self::AUTO_CORRECT_DISTANCE_KM) {
            $this->assertWithinStateBounds($lat, $lng, $state, $strict);

            return [
                'lat' => $lat,
                'lng' => $lng,
                'corrected' => false,
                'message' => null,
                'reference_label' => $reference['label'],
            ];
        }

        if ($strict && ! $autoCorrect) {
            throw ValidationException::withMessages([
                'departure_lat' => "As coordenadas informadas ficam a {$distanceKm} km do centro de {$cityName} ({$state}). "
                    ."Esperado próximo de {$reference['latitude']}, {$reference['longitude']}.",
            ]);
        }

        if ($autoCorrect) {
            return [
                'lat' => $reference['latitude'],
                'lng' => $reference['longitude'],
                'corrected' => true,
                'message' => "Coordenadas corrigidas automaticamente ({$distanceKm} km de diferença). "
                    ."Centro geocodificado: {$reference['label']}.",
                'reference_label' => $reference['label'],
            ];
        }

        $this->assertWithinStateBounds($lat, $lng, $state, $strict);

        return [
            'lat' => $lat,
            'lng' => $lng,
            'corrected' => false,
            'message' => null,
            'reference_label' => $reference['label'],
        ];
    }

    /**
     * Aplica normalização em um registro City (save, seed, migration).
     *
     * @return array{corrected: bool, message: ?string}
     */
    public function normalizeCity(City $city, bool $autoCorrect = true): array
    {
        if (! $city->rides_enabled || trim((string) $city->name) === '' || trim((string) $city->state) === '') {
            return ['corrected' => false, 'message' => null];
        }

        $result = $this->normalize(
            $city->departure_lat,
            $city->departure_lng,
            (string) $city->name,
            (string) $city->state,
            $autoCorrect,
            strict: false,
        );

        if ($result['corrected']) {
            $city->departure_lat = $result['lat'];
            $city->departure_lng = $result['lng'];

            if (empty($city->departure_address) && $result['reference_label']) {
                $city->departure_address = $result['reference_label'];
            }
        }

        return [
            'corrected' => $result['corrected'],
            'message' => $result['message'],
        ];
    }

    /**
     * @return array{latitude: float, longitude: float, label: string}|null
     */
    public function geocodeCityCenter(string $cityName, string $state): ?array
    {
        if (! $this->mapbox->isConfigured()) {
            return null;
        }

        $needleCity = mb_strtolower(trim($cityName));
        $needleState = mb_strtolower(trim($state));

        if ($needleCity === '' || $needleState === '') {
            return null;
        }

        $search = $this->mapbox->searchWithMeta("{$cityName}, {$state}, Brasil");

        foreach ($search['results'] as $result) {
            $label = mb_strtolower($result['label']);

            if (str_contains($label, $needleCity) && str_contains($label, $needleState)) {
                return [
                    'latitude' => $result['latitude'],
                    'longitude' => $result['longitude'],
                    'label' => $result['label'],
                ];
            }
        }

        $retry = $this->mapbox->searchWithMeta("{$cityName}, {$state}");

        foreach ($retry['results'] as $result) {
            $label = mb_strtolower($result['label']);

            if (str_contains($label, $needleCity)) {
                return [
                    'latitude' => $result['latitude'],
                    'longitude' => $result['longitude'],
                    'label' => $result['label'],
                ];
            }
        }

        return null;
    }

    private function normalizeBrazilCoordinate(?float $value, bool $isLatitude): ?float
    {
        if ($value === null) {
            return null;
        }

        if ($value > 0) {
            $value = -abs($value);
        }

        return $value;
    }

    private function assertWithinStateBounds(?float $lat, ?float $lng, string $state, bool $strict): void
    {
        if (! $strict || $lat === null || $lng === null) {
            return;
        }

        $bounds = self::STATE_BOUNDS[$state] ?? null;

        if ($bounds === null) {
            return;
        }

        $withinLat = $lat >= $bounds['lat'][0] && $lat <= $bounds['lat'][1];
        $withinLng = $lng >= $bounds['lng'][0] && $lng <= $bounds['lng'][1];

        if (! $withinLat || ! $withinLng) {
            throw ValidationException::withMessages([
                'departure_lat' => "Coordenadas fora dos limites esperados para {$state}. "
                    .'Verifique latitude e longitude (ex.: RS usa longitude entre -57 e -49).',
            ]);
        }
    }

    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return round($earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a)), 1);
    }
}
