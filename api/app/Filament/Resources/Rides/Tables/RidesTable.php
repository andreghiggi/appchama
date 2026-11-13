<?php

namespace App\Filament\Resources\Rides\Tables;

use App\Support\StatusLabels;
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
                TextColumn::make('passenger.name')
                    ->label('Passageiro')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('passenger.phone')
                    ->label('Tel. passageiro')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('driver.user.name')
                    ->label('Motorista')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('city.name')
                    ->label('Cidade')
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => StatusLabels::rideStatus($state))
                    ->sortable(),
                TextColumn::make('estimated_fare')
                    ->label('Estimativa')
                    ->formatStateUsing(fn ($state) => $state !== null ? 'R$ '.number_format((float) $state, 2, ',', '.') : '—')
                    ->sortable(),
                TextColumn::make('final_fare')
                    ->label('Final')
                    ->formatStateUsing(fn ($state) => $state !== null ? 'R$ '.number_format((float) $state, 2, ',', '.') : '—')
                    ->sortable(),
                TextColumn::make('driver_net_amount')
                    ->label('Líquido motorista')
                    ->formatStateUsing(fn ($state) => $state !== null ? 'R$ '.number_format((float) $state, 2, ',', '.') : '—')
                    ->toggleable(),
                TextColumn::make('payment_status')
                    ->label('Pagamento')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'pending' => 'Pendente',
                        'paid' => 'Pago',
                        'failed' => 'Falhou',
                        default => $state ?? '—',
                    }),
                TextColumn::make('payment_method')
                    ->label('Método')
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'pix' => 'Pix',
                        'credit_card' => 'Cartão',
                        'direct_driver' => 'Direto',
                        default => $state ?? '—',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('requested_at')
                    ->label('Solicitada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('requested_at', 'desc')
            ->filters([
                SelectFilter::make('status')->options([
                    'requested' => 'Solicitada',
                    'searching' => 'Buscando',
                    'accepted' => 'Aceita',
                    'driver_arrived' => 'Motorista chegou',
                    'in_progress' => 'Em andamento',
                    'awaiting_payment' => 'Aguardando pagamento',
                    'completed' => 'Concluída',
                    'canceled_by_passenger' => 'Cancelada (passageiro)',
                    'canceled_by_driver' => 'Cancelada (motorista)',
                    'no_drivers_available' => 'Sem motoristas',
                ]),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->emptyStateHeading('Nenhuma corrida ainda')
            ->emptyStateDescription('As corridas solicitadas pelos apps aparecerão aqui.');
    }
}
