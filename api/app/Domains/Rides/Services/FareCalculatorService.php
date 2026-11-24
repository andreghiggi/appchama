<?php

namespace App\Domains\Rides\Services;

use App\Domains\Maps\Services\MapboxService;
use App\Models\City;

class FareCalculatorService
{
    public function __construct(private MapboxService $mapbox) {}

    public function estimate(float $originLat, float $originLng, float $destLat, float $destLng, City $city): array
    {
        $route = $this->mapbox->directions($originLat, $originLng, $destLat, $destLng);

        $distanceKm = $route['distance_km'];
        $durationMin = $route['duration_min'];
        $fare = $this->calculate($city, $distanceKm, $durationMin);

        return [
            'distance_km' => $distanceKm,
            'duration_min' => $durationMin,
            'estimated_fare' => $fare,
            'polyline' => $route['polyline'],
            'coordinates' => $route['coordinates'],
        ];
    }

    public function calculateFinal(City $city, float $distanceKm, float $durationMin): float
    {
        return $this->calculate($city, $distanceKm, $durationMin);
    }

    public function calculateFromLocations(City $city, $locations, ?\DateTimeInterface $startedAt): array
    {
        $distanceKm = $this->sumSegmentDistance($locations);
        $durationMin = $startedAt ? now()->diffInMinutes($startedAt) : 0;

        return [
            'distance_km' => round($distanceKm, 2),
            'duration_min' => $durationMin,
            'final_fare' => $this->calculate($city, $distanceKm, $durationMin),
        ];
    }

    private function calculate(City $city, float $distanceKm, float $durationMin): float
    {
        $fare = (float) $city->base_fare
            + $this->distanceCharge($city, $distanceKm)
            + ($durationMin * (float) $city->price_per_min);

        return round(max($fare, (float) $city->min_fare), 2);
    }

    /**
     * Valor da distância. Sem faixas: km x valor por km. Com faixas (progressivo, como imposto de renda): cada km é
     * cobrado pelo valor da faixa em que ele cai. Ex.: R$ 4,00/km, a partir do km 5 R$ 3,50, a partir do km 10 R$ 3,00:
     * 12 km = 5x4,00 + 5x3,50 + 2x3,00 = R$ 43,50. Não há "degrau": o preço total só cresce com a distância.
     */
    public function distanceCharge(City $city, float $km): float
    {
        $km = max(0.0, $km);
        $preco = (float) $city->price_per_km;
        $faixas = $this->tiers($city);

        if ($faixas === []) {
            return $km * $preco;
        }

        $total = 0.0;
        $inicio = 0.0;
        foreach ($faixas as $faixa) {
            if ($km <= $inicio) {
                break;
            }
            $total += (min($km, $faixa['from_km']) - $inicio) * $preco;
            $inicio = $faixa['from_km'];
            $preco = $faixa['price'];
        }
        if ($km > $inicio) {
            $total += ($km - $inicio) * $preco;
        }

        return $total;
    }

    /** Faixas válidas, em ordem crescente de km (entradas inválidas ou repetidas são ignoradas). @return list<array{from_km: float, price: float}> */
    public function tiers(City $city): array
    {
        $out = [];
        foreach ((array) ($city->km_tiers ?? []) as $t) {
            $de = is_array($t) && isset($t['from_km']) && is_numeric($t['from_km']) ? (float) $t['from_km'] : null;
            $preco = is_array($t) && isset($t['price']) && is_numeric($t['price']) ? (float) $t['price'] : null;
            if ($de === null || $preco === null || $de <= 0 || $preco < 0) {
                continue;
            }
            $out[(string) $de] ??= ['from_km' => $de, 'price' => $preco]; // repetida: vale a primeira
        }
        ksort($out, SORT_NUMERIC);

        return array_values($out);
    }

    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function sumSegmentDistance($locations): float
    {
        $total = 0.0;
        $prev = null;

        foreach ($locations as $loc) {
            if ($prev) {
                $total += $this->haversine(
                    (float) $prev->lat,
                    (float) $prev->lng,
                    (float) $loc->lat,
                    (float) $loc->lng
                );
            }
            $prev = $loc;
        }

        return $total;
    }
}
