<?php

namespace App\Filament\Resources\Drivers\Tables;

use App\Domains\Drivers\Services\DriverRegistrationCodeService;
use App\Support\StatusLabels;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DriversTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')->label('Nome')->searchable(),
                TextColumn::make('user.phone')->label('Telefone')->searchable(),
                TextColumn::make('registration_source')
                    ->label('Origem')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'app' => 'App',
                        'admin' => 'Admin',
                        default => $state ?? '—',
                    }),
                IconColumn::make('cnh_document_path')
                    ->label('CNH')
                    ->boolean()
                    ->getStateUsing(fn ($record) => filled($record->cnh_document_path)),
                TextColumn::make('registration_code')
                    ->label('Código cadastro')
                    ->copyable()
                    ->searchable()
                    ->placeholder('—'),
                IconColumn::make('registration_code_confirmed_at')
                    ->label('Cód. confirmado')
                    ->boolean()
                    ->getStateUsing(fn ($record) => $record->registration_code_confirmed_at !== null),
                TextColumn::make('city.name')->label('Cidade'),
                TextColumn::make('background_check_status')
                    ->label('Antecedentes')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => StatusLabels::backgroundCheck($state)),
                TextColumn::make('subscription_status')
                    ->label('Mensalidade')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => StatusLabels::subscription($state)),
                IconColumn::make('online')->label('Online')->boolean(),
                TextColumn::make('rating_avg')->label('Nota'),
            ])
            ->filters([
                SelectFilter::make('registration_source')
                    ->label('Origem')
                    ->options([
                        'app' => 'App',
                        'admin' => 'Admin',
                    ]),
                SelectFilter::make('background_check_status')
                    ->label('Antecedentes')
                    ->options([
                        'pending' => 'Pendente',
                        'approved' => 'Aprovado',
                        'rejected' => 'Rejeitado',
                    ]),
            ])
            ->recordActions([
                Action::make('approveApp')
                    ->label('Aprovar cadastro')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn ($record) => $record->registration_source === 'app'
                        && $record->background_check_status === 'pending')
                    ->requiresConfirmation()
                    ->modalDescription('Confirma que a CNH e os dados foram verificados?')
                    ->action(function ($record): void {
                        app(DriverRegistrationCodeService::class)->approveAppDriver($record);

                        Notification::make()
                            ->title('Cadastro aprovado')
                            ->body('Motorista liberado para operar.')
                            ->success()
                            ->send();
                    }),
                Action::make('validateCode')
                    ->label('Validar código')
                    ->icon('heroicon-o-key')
                    ->color('success')
                    ->visible(fn ($record) => $record->registration_source !== 'app'
                        && $record->background_check_status === 'pending')
                    ->schema([
                        TextInput::make('registration_code')
                            ->label('Código informado pelo motorista')
                            ->placeholder('CHM-XXXXXX')
                            ->required()
                            ->maxLength(12),
                    ])
                    ->action(function ($record, array $data): void {
                        app(DriverRegistrationCodeService::class)
                            ->validateAndApprove($record, $data['registration_code']);

                        Notification::make()
                            ->title('Cadastro validado')
                            ->body('Motorista aprovado e liberado para operar.')
                            ->success()
                            ->send();
                    }),
                Action::make('reject')
                    ->label('Rejeitar')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn ($record) => $record->background_check_status === 'pending')
                    ->requiresConfirmation()
                    ->action(function ($record): void {
                        $record->update([
                            'background_check_status' => 'rejected',
                            'online' => false,
                        ]);
                        $record->user?->update(['status' => 'blocked']);
                    }),
                EditAction::make(),
            ])
            ->headerActions([
                Action::make('validateByCode')
                    ->label('Validar por código')
                    ->icon('heroicon-o-magnifying-glass')
                    ->schema([
                        TextInput::make('registration_code')
                            ->label('Código de cadastro')
                            ->placeholder('CHM-XXXXXX')
                            ->required()
                            ->maxLength(12),
                    ])
                    ->action(function (array $data): void {
                        $driver = app(DriverRegistrationCodeService::class)
                            ->findPendingByCode($data['registration_code']);

                        if (! $driver) {
                            Notification::make()
                                ->title('Código não encontrado')
                                ->body('Nenhum motorista pendente com este código.')
                                ->danger()
                                ->send();

                            return;
                        }

                        app(DriverRegistrationCodeService::class)
                            ->validateAndApprove($driver, $data['registration_code']);

                        Notification::make()
                            ->title('Cadastro validado')
                            ->body("Motorista {$driver->user?->name} aprovado.")
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
