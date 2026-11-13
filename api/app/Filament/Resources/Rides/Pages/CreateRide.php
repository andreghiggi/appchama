<?php

namespace App\Filament\Resources\Rides\Pages;

use App\Filament\Resources\Concerns\RedirectsToResourceIndex;
use App\Filament\Resources\Rides\RideResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRide extends CreateRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = RideResource::class;
}
