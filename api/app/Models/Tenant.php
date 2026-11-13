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
        'tagline',
        'primary_color',
        'theme',
        'logo_url',
        'contacts',
        'legal',
        'features',
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
            'theme' => 'array',
            'contacts' => 'array',
            'legal' => 'array',
            'features' => 'array',
        ];
    }

    public function domains(): HasMany
    {
        return $this->hasMany(TenantDomain::class);
    }

    /** Recursos que cada marca pode ligar ou desligar (padrao: tudo ligado, como no AppChama). */
    public static function defaultFeatures(): array
    {
        return [
            'driver_wallet' => true,
            'ratings' => true,
        ];
    }

    /** Cores do app (mesmos nomes de shared/theme.ts do app); o que faltar usa o padrao do AppChama. */
    public static function defaultTheme(): array
    {
        return [
            'ink' => '#14213D',
            'ink2' => '#1D2E52',
            'amber' => '#FF9F1C',
            'amberDark' => '#C97400',
            'onAmber' => '#14213D',
        ];
    }

    /**
     * Configuracao publica da marca, lida pelos apps no inicio.
     * NUNCA inclui chaves de pagamento, tokens ou dados internos.
     */
    public function brandConfig(): array
    {
        $theme = array_merge(self::defaultTheme(), array_filter($this->theme ?? []));
        if ($this->primary_color && empty($this->theme['amber'] ?? null)) {
            $theme['amber'] = $this->primary_color;
        }
        $logo = $this->logo_url;
        if ($logo && ! preg_match('#^https?://#i', $logo)) {
            $logo = url($logo);
        }

        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'tagline' => $this->tagline,
            'logo_url' => $logo,
            'theme' => $theme,
            'contacts' => (object) array_filter($this->contacts ?? []),
            'legal' => (object) array_filter($this->legal ?? []),
            'payment_methods' => $this->enabledPaymentMethods(),
            'features' => array_merge(self::defaultFeatures(), $this->features ?? []),
        ];
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
