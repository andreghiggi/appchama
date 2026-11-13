<?php

namespace App\Filament\Resources\BrandUsers\Pages;

use App\Domains\Tenants\Services\BrandUserService;
use App\Filament\Resources\BrandUsers\BrandUserResource;
use App\Filament\Resources\Concerns\RedirectsToResourceIndex;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Auth\Access\AuthorizationException;

class EditBrandUser extends EditRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = BrandUserResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $actor = auth()->user();
        if (! app(BrandUserService::class)->canManage($actor, $this->record)) {
            throw new AuthorizationException('Você não pode alterar este usuário.');
        }
        unset($data['tenant_id']); // a marca de um usuário não muda
        if (! $actor->is_platform_admin) {
            unset($data['role']);   // admin de marca não troca papéis
        }

        return $data;
    }
}
