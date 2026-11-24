<?php

namespace App\Filament\Resources\SupportTickets\Pages;

use App\Domains\Support\Services\SupportService;
use App\Filament\Resources\SupportTickets\SupportTicketResource;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewSupportTicket extends ViewRecord
{
    protected static string $resource = SupportTicketResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        // abrir o chamado = a equipe já viu
        if ($this->record->staff_unread) {
            $this->record->update(['staff_unread' => false]);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reply')
                ->label('Responder')
                ->icon('heroicon-o-paper-airplane')
                ->color('primary')
                ->form([
                    Textarea::make('body')->label('Sua resposta (o usuário recebe um aviso no celular)')
                        ->required()->minLength(2)->maxLength(2000)->rows(5),
                ])
                ->action(function (array $data): void {
                    app(SupportService::class)->staffReply(auth()->user(), $this->record, $data['body']);
                    $this->record->refresh();
                    Notification::make()->title('Resposta enviada')->success()->send();
                }),
            Action::make('close')
                ->label('Encerrar chamado')
                ->icon('heroicon-o-check-circle')
                ->color('gray')
                ->requiresConfirmation()
                ->visible(fn () => $this->record->status !== 'closed')
                ->action(function (): void {
                    app(SupportService::class)->close($this->record);
                    $this->record->refresh();
                    Notification::make()->title('Chamado encerrado')->success()->send();
                }),
        ];
    }
}
