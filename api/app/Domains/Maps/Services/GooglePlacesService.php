<?php

namespace App\Domains\Maps\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Busca de LOCAIS (hospital, supermercado, farmacia, loja...) pelo Google Places.
 * O Mapbox e o OpenStreetMap quase nao conhecem estabelecimentos de cidades pequenas.
 * Fica desligado se nao houver chave; nunca derruba a busca de enderecos.
 */
class GooglePlacesService
{
    public function isConfigured(): bool
    {
        return (bool) config('services.google.places_key');
    }

    /**
     * @return list<array{latitude: float, longitude: float, label: string}>
     */
    public function search(string $query, float $lat, float $lng, float $radiusKm): array
    {
        $key = config('services.google.places_key');
        $q = trim($query);

        if (! $key || mb_strlen($q) < 3) {
            return [];
        }

        // Mesma busca repetida (outros passageiros, digitacao parada) nao paga de novo.
        $cacheKey = 'places:'.md5(Str::ascii(mb_strtolower($q)).'|'.round($lat, 2).'|'.round($lng, 2));

        return Cache::remember($cacheKey, now()->addDay(), function () use ($key, $q, $lat, $lng, $radiusKm): array {
            try {
                $response = Http::timeout(6)
                    ->withHeaders([
                        'X-Goog-Api-Key' => $key,
                        'X-Goog-FieldMask' => 'places.displayName,places.formattedAddress,places.location',
                    ])
                    ->post('https://places.googleapis.com/v1/places:searchText', [
                        'textQuery' => $q,
                        'languageCode' => 'pt-BR',
                        'regionCode' => 'BR',
                        'maxResultCount' => 8,
                        'locationBias' => [
                            'circle' => [
                                'center' => ['latitude' => $lat, 'longitude' => $lng],
                                'radius' => min(50000.0, max(1000.0, $radiusKm * 1000)),
                            ],
                        ],
                    ]);

                if (! $response->successful()) {
                    Log::notice('Google Places recusou a busca: '.$response->status().' '.substr($response->body(), 0, 200));

                    return [];
                }

                return $this->parse($response->json('places', []));
            } catch (\Throwable $e) {
                Log::notice('Google Places falhou: '.$e->getMessage());

                return [];
            }
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $places
     * @return list<array{latitude: float, longitude: float, label: string}>
     */
    public function parse(array $places): array
    {
        $out = [];

        foreach ($places as $place) {
            $loc = $place['location'] ?? null;
            if (! is_array($loc) || ! isset($loc['latitude'], $loc['longitude'])) {
                continue;
            }

            $name = trim((string) ($place['displayName']['text'] ?? ''));
            $address = trim((string) ($place['formattedAddress'] ?? ''));
            $address = preg_replace('/,\s*Brasil$/u', '', $address) ?? $address;
            $label = trim($name !== '' && $address !== '' ? "{$name}, {$address}" : ($name !== '' ? $name : $address));

            if ($label === '') {
                continue;
            }

            $out[] = ['latitude' => (float) $loc['latitude'], 'longitude' => (float) $loc['longitude'], 'label' => $label];
        }

        return $out;
    }
}
