<?php

namespace App\Filament\Resources\Rides\Pages;

use App\Filament\Resources\Concerns\RedirectsToResourceIndex;
use App\Filament\Resources\Rides\RideResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRide extends EditRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = RideResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
