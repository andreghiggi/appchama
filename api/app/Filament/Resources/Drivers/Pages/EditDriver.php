<?php

namespace App\Filament\Resources\Drivers\Pages;

use App\Domains\Drivers\Services\DriverAdminService;
use App\Filament\Resources\Concerns\RedirectsToResourceIndex;
use App\Filament\Resources\Drivers\DriverResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditDriver extends EditRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = DriverResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return array_merge($data, app(DriverAdminService::class)->fillFormData($this->record));
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(DriverAdminService::class)->updateFromAdmin($record, $data);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
