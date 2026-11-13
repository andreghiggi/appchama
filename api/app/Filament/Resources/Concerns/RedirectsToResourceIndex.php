<?php

namespace App\Filament\Resources\Concerns;

trait RedirectsToResourceIndex
{
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
