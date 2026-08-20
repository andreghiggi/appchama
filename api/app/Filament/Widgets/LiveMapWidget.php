<?php

namespace App\Filament\Widgets;

use App\Models\Driver;
use Filament\Widgets\Widget;

class LiveMapWidget extends Widget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.live-map';

    protected function getViewData(): array
    {
        $drivers = Driver::query()
            ->with('user:id,name')
            ->where('online', true)
            ->whereNotNull('current_lat')
            ->get(['user_id', 'current_lat', 'current_lng', 'online']);

        return [
            'drivers' => $drivers,
        ];
    }
}
