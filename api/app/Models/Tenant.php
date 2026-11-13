<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'slug',
        'primary_color',
        'logo_url',
        'status',
        'asaas_api_key',
        'asaas_environment',
        'asaas_webhook_token',
        'platform_commission_pct',
        'payment_methods_enabled',
    ];

    protected function casts(): array
    {
        return [
            'asaas_api_key' => 'encrypted',
            'platform_commission_pct' => 'decimal:2',
            'payment_methods_enabled' => 'array',
        ];
    }

    public function domains(): HasMany
    {
        return $this->hasMany(TenantDomain::class);
    }

    public function defaultPaymentMethods(): array
    {
        return [
            'pix' => true,
            'credit_card' => true,
            'direct_driver' => true,
        ];
    }

    public function enabledPaymentMethods(): array
    {
        return array_merge(
            $this->defaultPaymentMethods(),
            $this->payment_methods_enabled ?? [],
        );
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }
}
