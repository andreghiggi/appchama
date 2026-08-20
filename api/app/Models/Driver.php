<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Driver extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'city_id',
        'cnh_number',
        'cnh_expiry',
        'background_check_status',
        'is_female_driver',
        'online',
        'current_lat',
        'current_lng',
        'last_location_at',
        'subscription_status',
        'rating_avg',
    ];

    protected function casts(): array
    {
        return [
            'cnh_expiry' => 'date',
            'is_female_driver' => 'boolean',
            'online' => 'boolean',
            'current_lat' => 'decimal:7',
            'current_lng' => 'decimal:7',
            'last_location_at' => 'datetime',
            'rating_avg' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class, 'driver_id', 'user_id');
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class, 'driver_id', 'user_id');
    }

    public function rides(): HasMany
    {
        return $this->hasMany(Ride::class, 'driver_id', 'user_id');
    }

    public function canReceiveRides(): bool
    {
        return $this->online
            && $this->background_check_status === 'approved'
            && ! in_array($this->subscription_status, ['suspended', 'overdue'], true)
            && $this->user?->status === 'active';
    }
}
