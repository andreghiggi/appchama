<?php

use App\Domains\Maps\Services\CityDepartureCoordinateService;
use App\Models\City;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (! app(CityDepartureCoordinateService::class)->geocodeCityCenter('Encantado', 'RS')) {
            return;
        }

        City::query()
            ->where('rides_enabled', true)
            ->orderBy('name')
            ->each(function (City $city): void {
                $result = app(CityDepartureCoordinateService::class)->normalizeCity($city, autoCorrect: true);

                if ($result['corrected']) {
                    $city->saveQuietly();
                }
            });
    }

    public function down(): void
    {
        // Dados de produção — não reverte coordenadas normalizadas.
    }
};
