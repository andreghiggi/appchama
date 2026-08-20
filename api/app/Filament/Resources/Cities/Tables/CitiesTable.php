<?php

namespace App\Filament\Resources\Cities\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CitiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Cidade')->searchable(),
                TextColumn::make('state')->label('UF'),
                TextColumn::make('base_fare')->label('Bandeirada')->money('BRL'),
                TextColumn::make('price_per_km')->label('R$/km')->money('BRL'),
                TextColumn::make('price_per_min')->label('R$/min')->money('BRL'),
                TextColumn::make('min_fare')->label('Mínima')->money('BRL'),
                IconColumn::make('active')->label('Ativa')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
