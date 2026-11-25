<?php

namespace App\Filament\Resources\Passengers\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PassengersTable
{
    /** 5551994928705 -> +55 (51) 99492-8705 (quando tem o formato esperado). */
    public static function formatPhone(?string $phone): string
    {
        $d = preg_replace('/\D+/', '', (string) $phone);
        if ($d === '') {
            return '—';
        }
        if (strlen($d) <= 11) {
            $d = '55'.$d;
        }
        if (strlen($d) === 13 && str_starts_with($d, '55')) {
            return sprintf('+55 (%s) %s-%s', substr($d, 2, 2), substr($d, 4, 5), substr($d, 9));
        }
        if (strlen($d) === 12 && str_starts_with($d, '55')) {
            return sprintf('+55 (%s) %s-%s', substr($d, 2, 2), substr($d, 4, 4), substr($d, 8));
        }

        return (string) $phone;
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nome')->searchable()->sortable(),
                TextColumn::make('phone')->label('Telefone')->searchable()
                    ->formatStateUsing(fn (?string $state) => self::formatPhone($state)),
                TextColumn::make('email')->label('E-mail')->searchable()->placeholder('—')->toggleable(),
                TextColumn::make('tenant.name')->label('Marca')
                    ->visible(fn () => (bool) auth()->user()?->is_platform_admin),
                TextColumn::make('status')->label('Situação')->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'active' => 'Ativo',
                        'blocked' => 'Bloqueado',
                        'pending' => 'Pendente',
                        default => $state ?? '—',
                    })
                    ->color(fn (?string $state) => match ($state) {
                        'active' => 'success',
                        'blocked' => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('passenger_rides_count')->label('Corridas')->sortable(),
                TextColumn::make('passenger_rides_max_requested_at')->label('Última corrida')
                    ->dateTime('d/m/Y H:i')->placeholder('—')->sortable(),
                TextColumn::make('created_at')->label('Cadastrado em')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('tenant_id')->label('Marca')->relationship('tenant', 'name')
                    ->visible(fn () => (bool) auth()->user()?->is_platform_admin),
                SelectFilter::make('status')->label('Situação')
                    ->options(['active' => 'Ativo', 'blocked' => 'Bloqueado', 'pending' => 'Pendente']),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Nenhum passageiro cadastrado ainda');
    }
}
