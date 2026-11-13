<?php

namespace App\Filament\Resources\Cities\Pages;

use App\Filament\Resources\Cities\CityResource;
use App\Filament\Resources\Cities\Concerns\EnsuresCityDepartureCoordinates;
use App\Filament\Resources\Concerns\RedirectsToResourceIndex;
use App\Models\Tenant;
use Filament\Resources\Pages\CreateRecord;

class CreateCity extends CreateRecord
{
    use EnsuresCityDepartureCoordinates;
    use RedirectsToResourceIndex;

    protected static string $resource = CityResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (! app()->bound('currentTenantId')) {
            \Filament\Notifications\Notification::make()->danger()
                ->title('Escolha a marca')
                ->body('Selecione uma marca no seletor "Marca:" do topo antes de criar uma cidade.')
                ->send();
            $this->halt();
        }
        $data['tenant_id'] = app('currentTenantId');

        return $this->ensureCityDepartureCoordinates($data);
    }

    protected function afterCreate(): void
    {
        $this->notifyCoordinateCorrection();
    }
}
