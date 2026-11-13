<?php

namespace App\Filament\Resources\BrandUsers\Pages;

use App\Filament\Resources\BrandUsers\BrandUserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBrandUsers extends ListRecords
{
    protected static string $resource = BrandUserResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Novo administrador')];
    }
}
