<?php

namespace App\Filament\Resources\Cities\Pages;

use App\Filament\Resources\Cities\CityResource;
use App\Filament\Resources\Cities\Concerns\EnsuresCityDepartureCoordinates;
use App\Filament\Resources\Concerns\RedirectsToResourceIndex;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCity extends EditRecord
{
    use EnsuresCityDepartureCoordinates;
    use RedirectsToResourceIndex;

    protected static string $resource = CityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->ensureCityDepartureCoordinates($data);
    }

    protected function afterSave(): void
    {
        $this->notifyCoordinateCorrection();
    }
}
