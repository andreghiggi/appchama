<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
    ];

    protected function casts(): array
    {
        return [
            'base_fare' => 'decimal:2',
            'price_per_km' => 'decimal:2',
            'price_per_min' => 'decimal:2',
            'min_fare' => 'decimal:2',
            'active' => 'boolean',
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
}
