<?php

namespace App\Filament\Resources\RidePayments\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RidePaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ride.passenger.name')->label('Passageiro'),
                TextColumn::make('ride.driver.user.name')->label('Motorista')->placeholder('—'),
                TextColumn::make('method')
                    ->label('Método')
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'pix' => 'Pix',
                        'credit_card' => 'Cartão',
                        'direct_driver' => 'Direto',
                        default => $state ?? '—',
                    }),
                TextColumn::make('amount')
                    ->label('Valor')
                    ->formatStateUsing(fn ($state) => 'R$ '.number_format((float) $state, 2, ',', '.')),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('ride.driver_net_amount')
                    ->label('Líquido motorista')
                    ->formatStateUsing(fn ($state) => $state !== null ? 'R$ '.number_format((float) $state, 2, ',', '.') : '—'),
                TextColumn::make('asaas_payment_id')->label('ID pagamento')->toggleable(),
                TextColumn::make('created_at')->label('Criado')->dateTime('d/m/Y H:i'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('method')->options([
                    'pix' => 'Pix',
                    'credit_card' => 'Cartão',
                    'direct_driver' => 'Direto',
                ]),
                SelectFilter::make('status')->options([
                    'pending' => 'Pendente',
                    'paid' => 'Pago',
                    'failed' => 'Falhou',
                ]),
            ]);
    }
}
