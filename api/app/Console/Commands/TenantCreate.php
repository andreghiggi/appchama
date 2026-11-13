<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\TenantDomain;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TenantCreate extends Command
{
    protected $signature = 'tenant:create
        {slug : apelido da marca (ex.: vaide55)}
        {name : nome da marca (ex.: Vaide55)}
        {--color=#FF9F1C : cor principal (#RRGGBB)}
        {--logo= : URL da logo}
        {--commission=15 : comissão da plataforma (%)}
        {--api-host= : endereço da API (ex.: api.vaide55.com.br)}
        {--admin-host= : endereço do painel (ex.: adm.vaide55.com.br)}
        {--site-host= : endereço do site (ex.: vaide55.com.br)}';

    protected $description = 'Cria uma marca (white label) com seus endereços';

    public function handle(): int
    {
        $slug = strtolower(trim($this->argument('slug')));
        if (! preg_match('/^[a-z0-9][a-z0-9-]{1,40}$/', $slug)) {
            $this->error('Apelido inválido: use letras minúsculas, números e hífen.');

            return self::FAILURE;
        }
        if (Tenant::query()->where('slug', $slug)->exists()) {
            $this->error("A marca '{$slug}' já existe.");

            return self::FAILURE;
        }
        $cor = (string) $this->option('color');
        if (! preg_match('/^#[0-9a-fA-F]{6}$/', $cor)) {
            $this->error('Cor inválida: use #RRGGBB.');

            return self::FAILURE;
        }

        $hosts = array_filter([
            'api' => $this->option('api-host'),
            'admin' => $this->option('admin-host'),
            'site' => $this->option('site-host'),
        ]);
        foreach ($hosts as $tipo => $host) {
            if (TenantDomain::query()->where('host', strtolower($host))->exists()) {
                $this->error("O endereço {$host} já pertence a outra marca.");

                return self::FAILURE;
            }
        }

        $tenant = DB::transaction(function () use ($slug, $cor, $hosts) {
            $tenant = Tenant::query()->create([
                'name' => $this->argument('name'),
                'slug' => $slug,
                'primary_color' => $cor,
                'logo_url' => $this->option('logo') ?: null,
                'status' => 'active',
                'asaas_environment' => 'sandbox',
                'platform_commission_pct' => (float) $this->option('commission'),
                'payment_methods_enabled' => ['pix' => true, 'credit_card' => true, 'direct_driver' => false],
            ]);
            foreach ($hosts as $tipo => $host) {
                TenantDomain::query()->create(['tenant_id' => $tenant->id, 'host' => strtolower($host), 'type' => $tipo]);
            }

            return $tenant;
        });

        $this->info("Marca criada: {$tenant->name} ({$tenant->slug}) id={$tenant->id}");
        foreach ($hosts as $tipo => $host) {
            $this->line("  {$tipo}: {$host}");
        }
        $this->line('Próximos passos: cidades, conta Asaas (sandbox), administradores da marca.');

        return self::SUCCESS;
    }
}
