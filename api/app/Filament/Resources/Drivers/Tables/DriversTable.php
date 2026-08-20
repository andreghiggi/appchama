<?php

namespace App\Filament\Resources\Drivers\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
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
                TextColumn::make('user.phone')->label('Telefone'),
                TextColumn::make('city.name')->label('Cidade'),
                TextColumn::make('background_check_status')->label('Antecedentes')->badge(),
                TextColumn::make('subscription_status')->label('Mensalidade')->badge(),
                IconColumn::make('online')->label('Online')->boolean(),
                TextColumn::make('rating_avg')->label('Nota'),
            ])
            ->filters([
                SelectFilter::make('background_check_status')
                    ->label('Antecedentes')
                    ->options([
                        'pending' => 'Pendente',
                        'approved' => 'Aprovado',
                        'rejected' => 'Rejeitado',
                    ]),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Aprovar')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn ($record) => $record->background_check_status === 'pending')
                    ->action(function ($record): void {
                        $record->update(['background_check_status' => 'approved']);
                        $record->user?->update(['status' => 'active']);
                        $record->update(['subscription_status' => 'active']);
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
