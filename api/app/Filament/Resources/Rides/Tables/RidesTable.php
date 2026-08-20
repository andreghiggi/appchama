<?php

namespace App\Filament\Resources\Rides\Tables;

use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RidesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('passenger.name')->label('Passageiro')->searchable(),
                TextColumn::make('driver.user.name')->label('Motorista')->placeholder('—'),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('estimated_fare')->label('Estimativa')->money('BRL'),
                TextColumn::make('final_fare')->label('Final')->money('BRL'),
                TextColumn::make('requested_at')->label('Solicitada')->dateTime('d/m/Y H:i'),
            ])
            ->defaultSort('requested_at', 'desc')
            ->filters([
                SelectFilter::make('status')->options([
                    'searching' => 'Buscando',
                    'accepted' => 'Aceita',
                    'in_progress' => 'Em corrida',
                    'completed' => 'Concluída',
                    'canceled_by_passenger' => 'Cancelada (passageiro)',
                    'canceled_by_driver' => 'Cancelada (motorista)',
                    'no_drivers_available' => 'Sem motoristas',
                ]),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}
