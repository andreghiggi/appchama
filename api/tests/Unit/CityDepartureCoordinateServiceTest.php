<?php

namespace Tests\Unit;

use App\Domains\Maps\Services\CityDepartureCoordinateService;
use App\Domains\Maps\Services\MapboxService;
use App\Models\City;
use Tests\TestCase;

class CityDepartureCoordinateServiceTest extends TestCase
{
    public function test_auto_corrects_encantado_longitude_typo(): void
    {
        $mapbox = $this->createMock(MapboxService::class);
        $mapbox->method('isConfigured')->willReturn(true);
        $mapbox->method('searchWithMeta')->willReturn([
            'results' => [[
                'latitude' => -29.237947,
                'longitude' => -51.868965,
                'label' => 'Encantado, Rio Grande do Sul, Brasil',
            ]],
            'filtered_count' => 0,
            'mapbox_count' => 1,
        ]);

        $service = new CityDepartureCoordinateService($mapbox);

        $result = $service->normalize(-29.2356, -52.8711, 'Encantado', 'RS');

        $this->assertTrue($result['corrected']);
        $this->assertSame(-51.868965, $result['lng']);
        $this->assertSame(-29.237947, $result['lat']);
    }

    public function test_keeps_coordinates_when_close_to_geocoded_center(): void
    {
        $mapbox = $this->createMock(MapboxService::class);
        $mapbox->method('isConfigured')->willReturn(true);
        $mapbox->method('searchWithMeta')->willReturn([
            'results' => [[
                'latitude' => -28.8436,
                'longitude' => -51.8908,
                'label' => 'Guaporé, Rio Grande do Sul, Brasil',
            ]],
            'filtered_count' => 0,
            'mapbox_count' => 1,
        ]);

        $service = new CityDepartureCoordinateService($mapbox);

        $result = $service->normalize(-28.8436, -51.8908, 'Guaporé', 'RS');

        $this->assertFalse($result['corrected']);
        $this->assertSame(-51.8908, $result['lng']);
    }

    public function test_fills_missing_coordinates_from_geocoder(): void
    {
        $mapbox = $this->createMock(MapboxService::class);
        $mapbox->method('isConfigured')->willReturn(true);
        $mapbox->method('searchWithMeta')->willReturn([
            'results' => [[
                'latitude' => -29.237947,
                'longitude' => -51.868965,
                'label' => 'Encantado, Rio Grande do Sul, Brasil',
            ]],
            'filtered_count' => 0,
            'mapbox_count' => 1,
        ]);

        $service = new CityDepartureCoordinateService($mapbox);

        $result = $service->normalize(null, null, 'Encantado', 'RS');

        $this->assertTrue($result['corrected']);
        $this->assertSame(-51.868965, $result['lng']);
    }

    public function test_normalize_city_updates_model(): void
    {
        $mapbox = $this->createMock(MapboxService::class);
        $mapbox->method('isConfigured')->willReturn(true);
        $mapbox->method('searchWithMeta')->willReturn([
            'results' => [[
                'latitude' => -29.237947,
                'longitude' => -51.868965,
                'label' => 'Encantado, Rio Grande do Sul, Brasil',
            ]],
            'filtered_count' => 0,
            'mapbox_count' => 1,
        ]);

        $service = new CityDepartureCoordinateService($mapbox);

        $city = new City([
            'name' => 'Encantado',
            'state' => 'RS',
            'rides_enabled' => true,
            'departure_lat' => -29.2356,
            'departure_lng' => -52.8711,
        ]);

        $result = $service->normalizeCity($city);

        $this->assertTrue($result['corrected']);
        $this->assertSame(-51.868965, (float) $city->departure_lng);
    }
}
