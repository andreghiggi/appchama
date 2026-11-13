<?php

namespace App\Observers;

use App\Domains\Maps\Services\CityDepartureCoordinateService;
use App\Models\City;

class CityObserver
{
    public function saving(City $city): void
    {
        if (! $city->rides_enabled) {
            return;
        }

        app(CityDepartureCoordinateService::class)->normalizeCity($city, autoCorrect: true);
    }
}
