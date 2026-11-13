<?php

namespace App\Filament\Resources\Withdrawals\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class WithdrawalsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('driver.user.name')->label('Motorista')->searchable(),
                TextColumn::make('amount')
                    ->label('Valor')
                    ->formatStateUsing(fn ($state) => 'R$ '.number_format((float) $state, 2, ',', '.')),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'pending' => 'Pendente',
                        'processing' => 'Processando',
                        'done' => 'Concluído',
                        'failed' => 'Falhou',
                        default => $state ?? '—',
                    }),
                TextColumn::make('pix_key')->label('Chave Pix')->limit(20),
                TextColumn::make('asaas_transfer_id')->label('ID Asaas')->toggleable(),
                TextColumn::make('fail_reason')->label('Erro')->limit(40)->toggleable(),
                TextColumn::make('created_at')->label('Solicitado')->dateTime('d/m/Y H:i'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->options([
                    'pending' => 'Pendente',
                    'processing' => 'Processando',
                    'done' => 'Concluído',
                    'failed' => 'Falhou',
                ]),
            ]);
    }
}
