<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUuids, Notifiable;

    protected $fillable = [
        'tenant_id',
        'name',
        'phone',
        'email',
        'cpf',
        'password',
        'role',
        'status',
        'is_platform_admin',
        'avatar_url',
        'home_address',
        'home_address_number',
        'home_postal_code',
        'home_city',
        'home_state',
        'home_lat',
        'home_lng',
        'asaas_customer_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_platform_admin' => 'boolean',
            'home_lat' => 'decimal:7',
            'home_lng' => 'decimal:7',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function driver(): HasOne
    {
        return $this->hasOne(Driver::class);
    }

    public function passengerRides(): HasMany
    {
        return $this->hasMany(Ride::class, 'passenger_id');
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if (! ($this->isAdmin() && $this->status === 'active')) {
            return false;
        }

        // Administrador da plataforma (AppChama): entra em qualquer endereço e vê tudo.
        if ($this->is_platform_admin) {
            return true;
        }

        // Administrador/suporte de uma marca: só no endereço da própria marca.
        return app()->bound('tenantFromHost')
            && app()->bound('currentTenantId')
            && app('currentTenantId') === $this->tenant_id;
    }

    public function isPlatformAdmin(): bool
    {
        return (bool) $this->is_platform_admin;
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'support'], true);
    }

    public function isDriver(): bool
    {
        return $this->role === 'driver';
    }

    public function isPassenger(): bool
    {
        return $this->role === 'passenger';
    }

    public function hasPaymentProfile(): bool
    {
        return filled($this->cpf)
            && filled($this->email)
            && filled($this->home_address)
            && filled($this->home_address_number)
            && filled($this->home_postal_code)
            && filled($this->home_city)
            && filled($this->home_state);
    }
}
