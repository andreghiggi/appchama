<?php

namespace App\Filament\Resources\SupportTickets\Tables;

use App\Models\SupportTicket;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SupportTicketsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('user:id,name,phone,role'))
            ->defaultSort('last_message_at', 'desc')
            ->columns([
                IconColumn::make('staff_unread')->label('Novo')->boolean()
                    ->trueIcon('heroicon-s-bell-alert')->falseIcon('')
                    ->trueColor('warning'),
                TextColumn::make('last_message_at')->label('Última mensagem')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('user.name')->label('Quem abriu')->searchable(),
                TextColumn::make('user.role')->label('É')->badge()
                    ->formatStateUsing(fn (?string $state) => $state === 'driver' ? 'Motorista' : 'Passageiro'),
                TextColumn::make('category')->label('Assunto')
                    ->formatStateUsing(fn (?string $state) => SupportTicket::CATEGORIES[$state] ?? $state),
                TextColumn::make('subject')->label('Resumo')->limit(60)->searchable(),
                TextColumn::make('status')->label('Situação')->badge()
                    ->formatStateUsing(fn (?string $state) => SupportTicket::STATUSES[$state] ?? $state)
                    ->color(fn (?string $state) => match ($state) {
                        'open' => 'warning',
                        'in_progress' => 'info',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')->label('Situação')->options(SupportTicket::STATUSES)
                    ->default('open'),
                SelectFilter::make('category')->label('Assunto')->options(SupportTicket::CATEGORIES),
            ])
            ->recordActions([
                ViewAction::make()->label('Abrir e responder'),
            ]);
    }
}
