<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Observers\CityObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy(CityObserver::class)]
class City extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'tenant_id',
        'name',
        'state',
        'base_fare',
        'price_per_km',
        'price_per_min',
        'min_fare',
        'active',
        'rides_enabled',
        'departure_address',
        'departure_lat',
        'departure_lng',
    ];

    protected function casts(): array
    {
        return [
            'base_fare' => 'decimal:2',
            'price_per_km' => 'decimal:2',
            'price_per_min' => 'decimal:2',
            'min_fare' => 'decimal:2',
            'active' => 'boolean',
            'rides_enabled' => 'boolean',
            'departure_lat' => 'decimal:7',
            'departure_lng' => 'decimal:7',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function drivers(): HasMany
    {
        return $this->hasMany(Driver::class);
    }

    public function rides(): HasMany
    {
        return $this->hasMany(Ride::class);
    }

    public function acceptsRides(): bool
    {
        return $this->active
            && $this->rides_enabled
            && $this->hasValidDepartureCoordinates();
    }

    public function hasValidDepartureCoordinates(): bool
    {
        $lat = self::normalizeCoordinate($this->departure_lat);
        $lng = self::normalizeCoordinate($this->departure_lng);

        if ($lat === null || $lng === null) {
            return false;
        }

        return $lat >= -90 && $lat <= 90
            && $lng >= -180 && $lng <= 180
            && ! ($lat == 0.0 && $lng == 0.0);
    }

    public static function normalizeCoordinate(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = str_replace(',', '.', trim((string) $value));

        if (! is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
    }
}
