<?php

namespace App\Filament\Resources\Cities\Schemas;

use Filament\Forms\Components\Repeater;
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
                        Repeater::make('km_tiers')
                            ->label('Preço por km menor em viagens longas (opcional)')
                            ->helperText('Cada linha diz: "a partir do km X, cada km passa a custar R$ Y". Vale só para os km que passam do ponto, sem degrau no preço. Exemplo: valor por km R$ 4,00; a partir do km 5 → R$ 3,50; a partir do km 10 → R$ 3,00. Uma viagem de 12 km = 5×4,00 + 5×3,50 + 2×3,00 = R$ 43,50 (mais bandeirada e minutos). Deixe vazio para cobrar o mesmo valor em todos os km.')
                            ->schema([
                                TextInput::make('from_km')->label('A partir do km')->required()->numeric()->minValue(0.1)->step(0.1)->suffix('km'),
                                TextInput::make('price')->label('Valor do km (R$)')->required()->numeric()->minValue(0)->step(0.01)->prefix('R$'),
                            ])
                            ->columns(2)
                            ->addActionLabel('Adicionar faixa')
                            ->defaultItems(0)
                            ->reorderable(false)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Section::make('Corrida com passageiro no carro')
                    ->description('Permite ao motorista aceitar a próxima corrida enquanto ainda leva um passageiro, depois de concluir parte do trajeto.')
                    ->schema([
                        Toggle::make('chain_rides_enabled')
                            ->label('Permitir nesta cidade')
                            ->default(true),
                        TextInput::make('chain_min_progress_pct')
                            ->label('Trajeto já concluído para liberar')
                            ->numeric()->minValue(1)->maxValue(95)->step(1)->default(20)->suffix('%')
                            ->helperText('Padrão 20%: o motorista só recebe a próxima depois de fazer 20% do caminho até o destino.'),
                        TextInput::make('chain_max_pickup_km')
                            ->label('Embarque da próxima: até quantos km do destino atual')
                            ->numeric()->minValue(0.5)->maxValue(50)->step(0.5)->default(5)->suffix('km')
                            ->helperText('A nova corrida só é oferecida se o embarque ficar perto de onde o motorista vai deixar o passageiro atual.'),
                    ])
                    ->columns(3),
            ]);
    }
}
