<?php

namespace App\Filament\Widgets;

use App\Models\City;
use App\Models\Ride;
use App\Models\Scopes\TenantScope;
use Filament\Widgets\Concerns\CanPoll;
use Filament\Widgets\Widget;

class DemandHeatmapWidget extends Widget
{
    use CanPoll;

    protected static ?int $sort = 5;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.demand-heatmap';

    protected function getViewData(): array
    {
        $city = City::query()
            ->where('active', true)
            ->whereNotNull('departure_lat')
            ->orderBy('name')
            ->first();

        $centerLat = (float) ($city?->departure_lat ?? -28.8436);
        $centerLng = (float) ($city?->departure_lng ?? -51.8908);

        $points = Ride::query()
            ->where('requested_at', '>=', now()->subDays(30))
            ->whereNotNull('origin_lat')
            ->whereNotNull('origin_lng')
            ->get(['origin_lat', 'origin_lng'])
            ->map(fn (Ride $ride) => [(float) $ride->origin_lng, (float) $ride->origin_lat])
            ->filter(fn (array $point) => $point !== [0.0, 0.0] && abs($point[1]) <= 90 && abs($point[0]) <= 180)
            ->values()
            ->all();

        return [
            'mapboxToken' => config('services.mapbox.token'),
            'centerLat' => $centerLat,
            'centerLng' => $centerLng,
            'cityName' => $city?->name,
            'points' => $points,
            'pointCount' => count($points),
            'widgetId' => $this->getId(),
        ];
    }

    protected function getPollingInterval(): ?string
    {
        return '60s';
    }
}
