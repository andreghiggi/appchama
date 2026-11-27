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

    public const AVISO_TELEFONE_DE_PASSAGEIRO = 'Este telefone já é de uma conta de passageiro e não pode ser usado em um cadastro de motorista.';

    /** Regra do telefone: motorista pode tambem ser passageiro, mas quem ja e passageiro nao vira motorista com o mesmo numero. */
    public static function telefoneDePassageiro(string $tenantId, string $phone): bool
    {
        return static::query()->where('tenant_id', $tenantId)->where('phone', $phone)->where('role', 'passenger')->exists();
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

    /** O Asaas (cartao/checkout) recusa nome de uma palavra so: exige nome e sobrenome. */
    public function hasFullName(): bool
    {
        return preg_match('/\S+\s+\S+/u', trim((string) $this->name)) === 1;
    }

    /** Pix no Asaas pede so nome completo e CPF valido (o endereco nao e obrigatorio). */
    public function hasPixProfile(): bool
    {
        return $this->hasFullName() && filled($this->cpf) && \App\Support\Cpf::isValid((string) $this->cpf);
    }

    /**
     * O que ainda falta o passageiro informar para pagar: so isso e pedido no app (o resto ja veio do cadastro).
     *
     * @return list<string> chaves: name, cpf, email, home_address, home_address_number, home_postal_code, home_city, home_state
     */
    public function missingPaymentFields(string $method = 'pix'): array
    {
        $falta = [];
        if (! $this->hasFullName()) {
            $falta[] = 'name';
        }
        if (blank($this->cpf) || ! \App\Support\Cpf::isValid((string) $this->cpf)) {
            $falta[] = 'cpf';
        }
        if ($method === 'credit_card') {
            foreach (['email', 'home_address', 'home_address_number', 'home_postal_code', 'home_city', 'home_state'] as $campo) {
                if (blank($this->{$campo})) {
                    $falta[] = $campo;
                }
            }
        }

        return $falta;
    }

    public function hasPaymentProfile(): bool
    {
        return $this->hasFullName()
            && filled($this->cpf)
            && filled($this->email)
            && filled($this->home_address)
            && filled($this->home_address_number)
            && filled($this->home_postal_code)
            && filled($this->home_city)
            && filled($this->home_state);
    }
}
