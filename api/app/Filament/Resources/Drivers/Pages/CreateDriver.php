<?php

namespace App\Filament\Resources\Drivers\Pages;

use App\Domains\Drivers\Services\DriverAdminService;
use App\Filament\Resources\Concerns\RedirectsToResourceIndex;
use App\Filament\Resources\Drivers\DriverResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateDriver extends CreateRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = DriverResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $driver = app(DriverAdminService::class)->createFromAdmin($data);

        Notification::make()
            ->title('Motorista cadastrado')
            ->body("Código de acesso: {$driver->registration_code}. Informe ao motorista junto com o telefone.")
            ->success()
            ->persistent()
            ->send();

        return $driver;
    }
}
