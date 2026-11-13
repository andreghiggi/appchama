<?php

namespace App\Filament\Resources\BrandUsers\Pages;

use App\Domains\Tenants\Services\BrandUserService;
use App\Filament\Resources\BrandUsers\BrandUserResource;
use App\Filament\Resources\Concerns\RedirectsToResourceIndex;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateBrandUser extends CreateRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = BrandUserResource::class;

    protected ?string $senhaTemporaria = null;

    protected function handleRecordCreation(array $data): Model
    {
        $actor = auth()->user();
        // Admin de marca: a marca é sempre a dele e o papel é sempre suporte (o servidor não confia no formulário).
        $tenantId = $actor->is_platform_admin ? ($data['tenant_id'] ?? null) : $actor->tenant_id;
        $role = $actor->is_platform_admin ? ($data['role'] ?? 'support') : 'support';

        [$user, $senha] = app(BrandUserService::class)->create($actor, (string) $tenantId, $data['name'], $data['email'], $role);
        $this->senhaTemporaria = $senha;

        return $user;
    }

    protected function afterCreate(): void
    {
        Notification::make()->success()->persistent()
            ->title('Usuário criado: '.$this->record->name)
            ->body("E-mail: {$this->record->email} — Senha temporária (aparece só agora): {$this->senhaTemporaria}")
            ->send();
    }

    protected function getCreatedNotification(): ?Notification
    {
        return null; // a notificação com a senha já é mostrada em afterCreate
    }
}
