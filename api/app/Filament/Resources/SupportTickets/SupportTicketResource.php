<?php

namespace App\Filament\Resources\SupportTickets;

use App\Filament\Resources\SupportTickets\Pages\ListSupportTickets;
use App\Filament\Resources\SupportTickets\Pages\ViewSupportTicket;
use App\Filament\Resources\SupportTickets\Tables\SupportTicketsTable;
use App\Models\SupportTicket;
use BackedEnum;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SupportTicketResource extends Resource
{
    protected static ?string $model = SupportTicket::class;

    protected static ?string $modelLabel = 'Chamado';

    protected static ?string $pluralModelLabel = 'Chamados';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|\UnitEnum|null $navigationGroup = 'Suporte';

    protected static ?int $navigationSort = 10;

    /** Bolinha no menu com os chamados que a equipe ainda não leu. */
    public static function getNavigationBadge(): ?string
    {
        $n = SupportTicket::query()->where('staff_unread', true)->where('status', '!=', 'closed')->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Chamado')->columns(3)->schema([
                TextEntry::make('user.name')->label('Quem abriu'),
                TextEntry::make('user.phone')->label('Telefone'),
                TextEntry::make('user.role')->label('É')
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'driver' => 'Motorista',
                        'passenger' => 'Passageiro',
                        default => (string) $state,
                    })->badge(),
                TextEntry::make('category')->label('Assunto')
                    ->formatStateUsing(fn (?string $state) => SupportTicket::CATEGORIES[$state] ?? $state),
                TextEntry::make('status')->label('Situação')->badge()
                    ->formatStateUsing(fn (?string $state) => SupportTicket::STATUSES[$state] ?? $state)
                    ->color(fn (?string $state) => match ($state) {
                        'open' => 'warning',
                        'in_progress' => 'info',
                        default => 'gray',
                    }),
                TextEntry::make('ride_id')->label('Corrida')
                    ->placeholder('Sem corrida ligada')
                    ->url(fn (SupportTicket $r) => $r->ride_id ? \App\Filament\Resources\Rides\RideResource::getUrl('view', ['record' => $r->ride_id]) : null),
            ]),
            Section::make('Conversa')->schema([
                RepeatableEntry::make('messages')->hiddenLabel()->schema([
                    TextEntry::make('author')->hiddenLabel()->badge()
                        ->formatStateUsing(fn (?string $state) => $state === 'staff' ? 'Equipe' : 'Usuário')
                        ->color(fn (?string $state) => $state === 'staff' ? 'success' : 'warning'),
                    TextEntry::make('created_at')->hiddenLabel()->dateTime('d/m/Y H:i'),
                    TextEntry::make('body')->hiddenLabel()->columnSpanFull()->prose(),
                ])->columns(2),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return SupportTicketsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSupportTickets::route('/'),
            'view' => ViewSupportTicket::route('/{record}'),
        ];
    }
}
