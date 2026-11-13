<?php

namespace App\Filament\Resources\Tenants\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TenantsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ColorColumn::make('primary_color')->label('Cor'),
                TextColumn::make('name')->label('Marca')->searchable(),
                TextColumn::make('slug')->label('Apelido'),
                TextColumn::make('status')->label('Situação')->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'active' ? 'Ativa' : 'Suspensa')
                    ->color(fn (string $state) => $state === 'active' ? 'success' : 'warning'),
                TextColumn::make('domains.host')->label('Endereços')->listWithLineBreaks(),
                TextColumn::make('platform_commission_pct')->label('Comissão')->suffix('%'),
            ])
            ->recordActions([EditAction::make()])
            ->defaultSort('name');
    }
}
