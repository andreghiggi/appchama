<?php

namespace App\Filament\Resources\Tenants\Pages;

use App\Filament\Resources\Tenants\TenantResource;
use Filament\Resources\Pages\CreateRecord;
use App\Filament\Resources\Concerns\RedirectsToResourceIndex;

class CreateTenant extends CreateRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = TenantResource::class;
}
