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
            + ($distanceKm * (float) $city->price_per_km)
            + ($durationMin * (float) $city->price_per_min);

        return round(max($fare, (float) $city->min_fare), 2);
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
