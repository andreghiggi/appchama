<?php

namespace App\Filament\Resources\Cities\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CityForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Cidade')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nome')
                            ->required()
                            ->maxLength(120),
                        TextInput::make('state')
                            ->label('UF')
                            ->required()
                            ->maxLength(2)
                            ->placeholder('SP'),
                        Toggle::make('active')
                            ->label('Ativa')
                            ->default(true),
                        Toggle::make('rides_enabled')
                            ->label('Habilitar corridas')
                            ->helperText('Libera a área de 50 km em torno do endereço de partida para passageiros pedirem corridas.')
                            ->default(false),
                        Toggle::make('driver_signup_enabled')
                            ->label('Aceita cadastro de motoristas pelo app')
                            ->helperText('Desligado: o motorista não consegue escolher esta cidade ao se cadastrar no app (passageiros não são afetados).')
                            ->default(true),
                    ])
                    ->columns(2),
                Section::make('Área de operação')
                    ->description('Obrigatório quando "Habilitar corridas" estiver marcado. Passageiros e destinos ficam limitados a 50 km deste ponto.')
                    ->schema([
                        TextInput::make('departure_address')
                            ->label('Endereço de partida')
                            ->required(fn ($get) => (bool) $get('rides_enabled'))
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('departure_lat')
                            ->label('Latitude')
                            ->required(fn ($get) => (bool) $get('rides_enabled'))
                            ->numeric()
                            ->minValue(-90)
                            ->maxValue(90)
                            ->step(0.0000001)
                            ->helperText('Entre -90 e 90 (ex.: -29.2356). Se vazio, preenchemos pelo Mapbox.'),
                        TextInput::make('departure_lng')
                            ->label('Longitude')
                            ->required(fn ($get) => (bool) $get('rides_enabled'))
                            ->numeric()
                            ->minValue(-180)
                            ->maxValue(180)
                            ->step(0.0000001)
                            ->helperText('Brasil: use valor negativo (ex.: RS ≈ -51.87, SP ≈ -46.63). Corrigimos automaticamente se estiver muito longe do centro da cidade.'),
                    ])
                    ->columns(2),
                Section::make('Tabela de preços')
                    ->description('Bandeirada (valor fixo inicial), valor por km, por minuto e tarifa mínima.')
                    ->schema([
                        TextInput::make('base_fare')
                            ->label('Bandeirada (R$)')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->prefix('R$'),
                        TextInput::make('price_per_km')
                            ->label('Valor por km (R$)')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->prefix('R$'),
                        TextInput::make('price_per_min')
                            ->label('Valor por minuto (R$)')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->prefix('R$'),
                        TextInput::make('min_fare')
                            ->label('Tarifa mínima (R$)')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->prefix('R$'),
                    ])
                    ->columns(2),
            ]);
    }
}
