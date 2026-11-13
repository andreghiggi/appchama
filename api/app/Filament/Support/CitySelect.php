<?php

namespace App\Filament\Support;

use App\Domains\Maps\Services\MapboxService;
use App\Models\City;
use App\Models\Scopes\TenantScope;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Builder;

class CitySelect
{
    public static function make(string $name = 'city_id'): Select
    {
        return Select::make($name)
            ->label('Cidade')
            ->searchable()
            ->preload()
            ->required()
            ->getSearchResultsUsing(fn (string $search): array => self::searchCities($search))
            ->getOptionLabelUsing(fn ($value): ?string => self::cityLabel($value))
            ->live()
            ->afterStateUpdated(function (?string $state, Set $set): void {
                self::applyCityDefaults($state, $set);
            });
    }

    /** @return array<string, string> */
    public static function searchCities(string $search): array
    {
        return self::cityQuery()
            ->when(filled($search), function (Builder $query) use ($search): void {
                $query->where(function (Builder $inner) use ($search): void {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('state', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (City $city) => [$city->id => self::formatCityLabel($city)])
            ->all();
    }

    public static function cityLabel(mixed $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        $city = self::cityQuery()->find($value);

        return $city ? self::formatCityLabel($city) : null;
    }

    public static function applyCityDefaults(?string $cityId, Set $set): void
    {
        if (! filled($cityId)) {
            return;
        }

        $city = self::cityQuery()->find($cityId);

        if (! $city) {
            return;
        }

        $lat = $city->departure_lat;
        $lng = $city->departure_lng;
        $address = $city->departure_address;

        if (($lat === null || $lng === null) && app(MapboxService::class)->isConfigured()) {
            $results = app(MapboxService::class)->search("{$city->name}, {$city->state}, Brasil");

            if ($results !== []) {
                $lat ??= $results[0]['latitude'];
                $lng ??= $results[0]['longitude'];
                $address ??= $results[0]['label'];
            }
        }

        if ($address) {
            $set('departure_address', $address);
        }

        if ($lat !== null) {
            $set('departure_lat', $lat);
        }

        if ($lng !== null) {
            $set('departure_lng', $lng);
        }
    }

    private static function cityQuery(): Builder
    {
        return City::query()
            ->where('active', true);
    }

    private static function formatCityLabel(City $city): string
    {
        return trim("{$city->name} - {$city->state}");
    }
}
