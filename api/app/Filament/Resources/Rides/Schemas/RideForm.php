<?php

namespace App\Filament\Resources\Rides\Schemas;

use App\Support\StatusLabels;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RideForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Corrida')
                    ->schema([
                        Placeholder::make('status')
                            ->label('Status')
                            ->content(fn ($record) => StatusLabels::rideStatus($record?->status)),
                        Placeholder::make('passenger')
                            ->label('Passageiro')
                            ->content(fn ($record) => $record?->passenger?->name ?? '—'),
                        Placeholder::make('driver')
                            ->label('Motorista')
                            ->content(fn ($record) => $record?->driver?->user?->name ?? '—'),
                        Placeholder::make('city')
                            ->label('Cidade')
                            ->content(fn ($record) => $record?->city?->name ?? '—'),
                        Placeholder::make('requested_at')
                            ->label('Solicitada')
                            ->content(fn ($record) => $record?->requested_at?->format('d/m/Y H:i') ?? '—'),
                        Placeholder::make('origin_address')
                            ->label('Embarque')
                            ->content(fn ($record) => $record?->origin_address ?? '—')
                            ->columnSpanFull(),
                        Placeholder::make('destination_address')
                            ->label('Destino')
                            ->content(fn ($record) => $record?->destination_address ?? '—')
                            ->columnSpanFull(),
                        Placeholder::make('estimated_fare')
                            ->label('Estimativa')
                            ->content(fn ($record) => $record?->estimated_fare !== null
                                ? 'R$ '.number_format((float) $record->estimated_fare, 2, ',', '.')
                                : '—'),
                        Placeholder::make('final_fare')
                            ->label('Valor final')
                            ->content(fn ($record) => $record?->final_fare !== null
                                ? 'R$ '.number_format((float) $record->final_fare, 2, ',', '.')
                                : '—'),
                        Placeholder::make('driver_net_amount')
                            ->label('Líquido motorista')
                            ->content(fn ($record) => $record?->driver_net_amount !== null
                                ? 'R$ '.number_format((float) $record->driver_net_amount, 2, ',', '.')
                                : '—'),
                        Placeholder::make('platform_fee_amount')
                            ->label('Comissão plataforma')
                            ->content(fn ($record) => $record?->platform_fee_amount !== null
                                ? 'R$ '.number_format((float) $record->platform_fee_amount, 2, ',', '.')
                                : '—'),
                        Placeholder::make('payment_status')
                            ->label('Pagamento')
                            ->content(fn ($record) => match ($record?->payment_status) {
                                'pending' => 'Pendente',
                                'paid' => 'Pago',
                                'failed' => 'Falhou',
                                default => $record?->payment_status ?? '—',
                            }),
                        Placeholder::make('payment_method')
                            ->label('Método')
                            ->content(fn ($record) => match ($record?->payment_method) {
                                'pix' => 'Pix',
                                'credit_card' => 'Cartão',
                                'direct_driver' => 'Direto ao motorista',
                                default => $record?->payment_method ?? '—',
                            }),
                    ])
                    ->columns(2),
            ]);
    }
}
