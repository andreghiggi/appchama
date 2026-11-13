<?php

namespace App\Filament\Resources\Tenants\Pages;

use App\Filament\Resources\Tenants\TenantResource;
use Filament\Resources\Pages\EditRecord;
use App\Filament\Resources\Concerns\RedirectsToResourceIndex;

class EditTenant extends EditRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = TenantResource::class;
}
