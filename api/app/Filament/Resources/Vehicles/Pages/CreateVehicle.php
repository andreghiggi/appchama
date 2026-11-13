<?php

namespace App\Filament\Resources\Vehicles\Pages;

use App\Domains\Drivers\Services\DriverAdminService;
use App\Filament\Resources\Concerns\RedirectsToResourceIndex;
use App\Filament\Resources\Vehicles\VehicleResource;
use App\Models\Driver;
use Filament\Resources\Pages\CreateRecord;

class CreateVehicle extends CreateRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = VehicleResource::class;

    protected function afterCreate(): void
    {
        $driver = Driver::query()->with(['user', 'subscription', 'city'])->find($this->record->driver_id);

        if ($driver) {
            app(DriverAdminService::class)->ensureMatchingReady($driver);
        }
    }
}
