<?php

namespace Tests\Unit;

use App\Domains\Maps\Services\ServiceAreaService;
use App\Models\City;
use Illuminate\Http\Exceptions\HttpResponseException;
use Tests\TestCase;

class ServiceAreaServiceTest extends TestCase
{
    private ServiceAreaService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.geocode_max_radius_km' => 50]);
        $this->service = app(ServiceAreaService::class);
    }

    private function guapore(): City
    {
        return new City([
            'id' => '11111111-1111-1111-1111-111111111111',
            'name' => 'Guaporé',
            'state' => 'RS',
            'active' => true,
            'rides_enabled' => true,
            'departure_address' => 'Centro Guaporé',
            'departure_lat' => -28.8436,
            'departure_lng' => -51.8908,
        ]);
    }

    private function encantado(): City
    {
        return new City([
            'id' => '22222222-2222-2222-2222-222222222222',
            'name' => 'Encantado',
            'state' => 'RS',
            'active' => true,
            'rides_enabled' => true,
            'departure_address' => 'Centro Encantado',
            'departure_lat' => -29.2356,
            'departure_lng' => -51.8711,
        ]);
    }

    public function test_resolve_selected_city_reports_distance_from_departure_center(): void
    {
        $resolved = $this->service->resolveSelectedCity($this->guapore(), 0, 0);

        $this->assertSame(-28.8436, $resolved['center']['latitude']);
        $this->assertFalse($resolved['within_service_area']);
        $this->assertGreaterThan(50, $resolved['distance_km']);
    }

    public function test_assert_within_service_area_accepts_city_center(): void
    {
        $city = $this->guapore();

        $this->service->assertWithinServiceArea(
            (float) $city->departure_lat,
            (float) $city->departure_lng,
            $city,
            null,
            'origem da corrida',
        );

        $this->assertTrue(true);
    }

    public function test_assert_within_service_area_rejects_coordinates_outside_radius(): void
    {
        $this->expectException(HttpResponseException::class);

        $this->service->assertWithinServiceArea(
            -23.5505,
            -46.6333,
            $this->guapore(),
            null,
            'origem da corrida',
        );
    }

    public function test_selected_city_validation_does_not_use_gps_inferred_city_mismatch_message(): void
    {
        try {
            $this->service->assertWithinServiceArea(
                -23.5505,
                -46.6333,
                $this->guapore(),
                null,
                'origem da corrida',
            );
            $this->fail('Expected validation failure for origin outside Guaporé radius.');
        } catch (HttpResponseException $e) {
            $message = $e->getResponse()->getData(true)['message'] ?? '';
            $this->assertStringContainsString('Guaporé', $message);
            $this->assertStringNotContainsString('pertence à área de', $message);
        }
    }
}
