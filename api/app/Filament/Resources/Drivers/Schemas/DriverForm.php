<?php

namespace App\Filament\Resources\Drivers\Schemas;

use App\Filament\Support\CitySelect;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class DriverForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dados pessoais')
                    ->schema([
                        TextInput::make('user_name')
                            ->label('Nome')
                            ->required()
                            ->maxLength(120),
                        TextInput::make('user_phone')
                            ->label('Telefone')
                            ->required()
                            ->tel()
                            ->placeholder('5511999990000')
                            ->maxLength(20),
                        TextInput::make('registration_code')
                            ->label('Código de cadastro')
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn (?string $state) => filled($state))
                            ->helperText('Gerado automaticamente no cadastro manual. Informe este código ao motorista para o primeiro acesso.'),
                        Placeholder::make('registration_source_label')
                            ->label('Origem do cadastro')
                            ->content(fn ($record) => match ($record?->registration_source) {
                                'app' => 'App (auto-cadastro)',
                                'admin' => 'Painel administrativo',
                                default => '—',
                            }),
                        Placeholder::make('cnh_document_link')
                            ->label('Documento CNH')
                            ->visible(fn ($record) => filled($record?->cnh_document_path))
                            ->content(fn ($record) => new HtmlString(
                                '<a href="'.route('admin.drivers.cnh-document', $record).'" target="_blank" class="text-primary-600 underline">Abrir documento enviado pelo motorista</a>'
                            )),
                    ])
                    ->columns(2),
                Section::make('Motorista')
                    ->schema([
                        CitySelect::make(),
                        TextInput::make('departure_address')
                            ->label('Endereço de partida')
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->helperText('Preenchido automaticamente ao selecionar a cidade. Pode ajustar manualmente.'),
                        TextInput::make('departure_lat')
                            ->label('Latitude de partida')
                            ->numeric()
                            ->step(0.0000001)
                            ->helperText('Herdada da cidade selecionada.'),
                        TextInput::make('departure_lng')
                            ->label('Longitude de partida')
                            ->numeric()
                            ->step(0.0000001)
                            ->helperText('Herdada da cidade selecionada.'),
                        TextInput::make('cnh_number')
                            ->label('CNH')
                            ->maxLength(20)
                            ->helperText('Opcional no cadastro; use "PENDENTE" se ainda não tiver o número.'),
                        DatePicker::make('cnh_expiry')
                            ->label('Validade da CNH')
                            ->native(false)
                            ->helperText('Opcional; padrão de 1 ano se não informada.'),
                        Select::make('background_check_status')
                            ->label('Antecedentes')
                            ->options([
                                'pending' => 'Pendente',
                                'approved' => 'Aprovado',
                                'rejected' => 'Rejeitado',
                            ])
                            ->default('approved')
                            ->required(),
                        Select::make('subscription_status')
                            ->label('Mensalidade')
                            ->options([
                                'pending' => 'Pendente',
                                'active' => 'Ativa',
                                'overdue' => 'Em atraso',
                                'suspended' => 'Suspensa',
                            ])
                            ->default('active')
                            ->required(),
                        Toggle::make('is_female_driver')
                            ->label('Motorista mulher'),
                    ])
                    ->columns(2),
                Section::make('Veículo')
                    ->schema([
                        TextInput::make('vehicle_plate')
                            ->label('Placa')
                            ->required()
                            ->maxLength(10)
                            ->placeholder('ABC1D23'),
                        TextInput::make('vehicle_model')
                            ->label('Modelo')
                            ->maxLength(80),
                        TextInput::make('vehicle_color')
                            ->label('Cor')
                            ->maxLength(40),
                        TextInput::make('vehicle_year')
                            ->label('Ano')
                            ->numeric()
                            ->minValue(1990)
                            ->maxValue((int) date('Y') + 1),
                    ])
                    ->columns(2),
            ]);
    }
}
