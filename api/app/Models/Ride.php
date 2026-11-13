<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ride extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'tenant_id',
        'city_id',
        'passenger_id',
        'driver_id',
        'status',
        'origin_lat',
        'origin_lng',
        'origin_address',
        'destination_lat',
        'destination_lng',
        'destination_address',
        'estimated_fare',
        'final_fare',
        'distance_km',
        'requested_at',
        'accepted_at',
        'started_at',
        'completed_at',
        'canceled_reason',
        'payment_status',
        'payment_method',
        'paid_at',
        'platform_fee_amount',
        'driver_net_amount',
    ];

    protected function casts(): array
    {
        return [
            'origin_lat' => 'decimal:7',
            'origin_lng' => 'decimal:7',
            'destination_lat' => 'decimal:7',
            'destination_lng' => 'decimal:7',
            'estimated_fare' => 'decimal:2',
            'final_fare' => 'decimal:2',
            'distance_km' => 'decimal:2',
            'requested_at' => 'datetime',
            'accepted_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'paid_at' => 'datetime',
            'platform_fee_amount' => 'decimal:2',
            'driver_net_amount' => 'decimal:2',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function passenger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'passenger_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_id', 'user_id');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(RideLocation::class);
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(RidePayment::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, [
            'requested', 'searching', 'accepted', 'driver_arrived', 'in_progress', 'awaiting_payment',
        ], true);
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }
}
