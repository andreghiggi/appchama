<?php

namespace App\Filament\Resources\Vehicles\Schemas;

use App\Models\Driver;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VehicleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Veículo')
                    ->schema([
                        Select::make('driver_id')
                            ->label('Motorista')
                            ->options(fn () => Driver::query()
                                ->with('user')
                                ->get()
                                ->mapWithKeys(fn (Driver $driver) => [
                                    $driver->user_id => trim(($driver->user?->name ?? 'Sem nome').' · '.($driver->user?->phone ?? '')),
                                ]))
                            ->searchable()
                            ->required(),
                        TextInput::make('plate')
                            ->label('Placa')
                            ->required()
                            ->maxLength(10)
                            ->placeholder('ABC1D23'),
                        TextInput::make('model')
                            ->label('Modelo')
                            ->required()
                            ->maxLength(60)
                            ->placeholder('Fiat Argo'),
                        TextInput::make('color')
                            ->label('Cor')
                            ->required()
                            ->maxLength(30)
                            ->placeholder('Prata'),
                        TextInput::make('year')
                            ->label('Ano')
                            ->numeric()
                            ->minValue(1990)
                            ->maxValue((int) date('Y') + 1),
                        TextInput::make('photo_url')
                            ->label('URL da foto')
                            ->url()
                            ->maxLength(255),
                    ])
                    ->columns(2),
            ]);
    }
}
