<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Driver extends Model
{
    use \App\Models\Concerns\BelongsToTenant;
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'registration_code',
        'registration_code_confirmed_at',
        'registration_validated_at',
        'registration_source',
        'city_id',
        'departure_address',
        'departure_lat',
        'departure_lng',
        'cnh_number',
        'cnh_expiry',
        'cnh_document_path',
        'background_check_status',
        'is_female_driver',
        'online',
        'current_lat',
        'current_lng',
        'last_location_at',
        'subscription_status',
        'rating_avg',
        'payout_pix_key',
        'payout_pix_key_type',
    ];

    protected function casts(): array
    {
        return [
            'cnh_expiry' => 'date',
            'registration_code_confirmed_at' => 'datetime',
            'registration_validated_at' => 'datetime',
            'is_female_driver' => 'boolean',
            'online' => 'boolean',
            'departure_lat' => 'decimal:7',
            'departure_lng' => 'decimal:7',
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

    public function wallet(): HasOne
    {
        return $this->hasOne(DriverWallet::class, 'driver_id', 'user_id');
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(DriverWithdrawal::class, 'driver_id', 'user_id');
    }

    public function canReceiveRides(): bool
    {
        return $this->online
            && $this->background_check_status === 'approved'
            && ! in_array($this->subscription_status, ['suspended', 'overdue'], true)
            && $this->user?->status === 'active';
    }

    public function tenantIdFromParent(): ?string
    {
        return \App\Models\User::withoutGlobalScopes()->whereKey($this->user_id)->value('tenant_id');
    }
}
