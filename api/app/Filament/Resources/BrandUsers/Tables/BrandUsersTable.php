<?php

namespace App\Filament\Resources\BrandUsers\Tables;

use App\Domains\Tenants\Services\BrandUserService;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BrandUsersTable
{
    public static function configure(Table $table): Table
    {
        $service = fn (): BrandUserService => app(BrandUserService::class);

        return $table
            ->columns([
                TextColumn::make('name')->label('Nome')->searchable(),
                TextColumn::make('email')->label('E-mail')->searchable(),
                TextColumn::make('tenant.name')->label('Marca')->visible(fn () => (bool) auth()->user()?->is_platform_admin),
                TextColumn::make('role')->label('Papel')->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'admin' ? 'Administrador' : 'Suporte'),
                TextColumn::make('status')->label('Situação')->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'active' ? 'Ativo' : 'Desativado')
                    ->color(fn (string $state) => $state === 'active' ? 'success' : 'danger'),
            ])
            ->filters([
                SelectFilter::make('tenant_id')->label('Marca')->relationship('tenant', 'name')
                    ->visible(fn () => (bool) auth()->user()?->is_platform_admin),
                SelectFilter::make('role')->label('Papel')->options(['admin' => 'Administrador', 'support' => 'Suporte']),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('redefinir_senha')
                    ->label('Redefinir senha')
                    ->icon('heroicon-o-key')
                    ->requiresConfirmation()
                    ->modalDescription('Uma nova senha temporária será gerada e a senha atual deixa de valer.')
                    ->visible(fn (User $record) => $service()->canManage(auth()->user(), $record))
                    ->action(function (User $record) use ($service): void {
                        $senha = $service()->resetPassword(auth()->user(), $record);
                        Notification::make()->success()->persistent()
                            ->title('Nova senha de '.$record->name)
                            ->body("Senha temporária (aparece só agora): {$senha}")
                            ->send();
                    }),
                Action::make('alternar_situacao')
                    ->label(fn (User $record) => $record->status === 'active' ? 'Desativar' : 'Reativar')
                    ->icon(fn (User $record) => $record->status === 'active' ? 'heroicon-o-no-symbol' : 'heroicon-o-check-circle')
                    ->color(fn (User $record) => $record->status === 'active' ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->visible(fn (User $record) => $service()->canManage(auth()->user(), $record) && auth()->id() !== $record->id)
                    ->action(function (User $record) use ($service): void {
                        $ativar = $record->status !== 'active';
                        $service()->setActive(auth()->user(), $record, $ativar);
                        Notification::make()->success()->title($ativar ? 'Usuário reativado' : 'Usuário desativado')->send();
                    }),
            ])
            ->defaultSort('name');
    }
}
