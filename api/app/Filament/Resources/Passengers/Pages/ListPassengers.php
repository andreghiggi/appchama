<?php

namespace App\Filament\Resources\Passengers\Pages;

use App\Filament\Resources\Passengers\PassengerResource;
use Filament\Resources\Pages\ListRecords;

class ListPassengers extends ListRecords
{
    protected static string $resource = PassengerResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
