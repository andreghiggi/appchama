<?php

namespace App\Filament\Resources\SupportTickets\Pages;

use App\Filament\Resources\Concerns\RedirectsToResourceIndex;
use App\Filament\Resources\SupportTickets\SupportTicketResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSupportTicket extends CreateRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = SupportTicketResource::class;
}
