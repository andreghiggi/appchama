<?php

namespace App\Console\Commands;

use App\Domains\Tenants\Services\BrandUserService;
use App\Models\Tenant;
use Illuminate\Console\Command;

class TenantAdmin extends Command
{
    protected $signature = 'tenant:admin {slug} {email} {name} {--role=admin : admin ou support}';

    protected $description = 'Cria um administrador (ou suporte) de uma marca; a senha temporária aparece uma única vez';

    public function handle(BrandUserService $service): int
    {
        $tenant = Tenant::query()->where('slug', $this->argument('slug'))->first();
        if (! $tenant) {
            $this->error('Marca não encontrada.');

            return self::FAILURE;
        }
        try {
            [$user, $senha] = $service->create(null, $tenant, $this->argument('name'), $this->argument('email'), (string) $this->option('role'));
        } catch (\Throwable $e) {
            $this->error($e instanceof \Illuminate\Validation\ValidationException ? collect($e->errors())->flatten()->implode(' ') : $e->getMessage());

            return self::FAILURE;
        }
        $this->info("Criado: {$user->name} <{$user->email}> ({$user->role}) em {$tenant->name}");
        $this->line("Endereço do painel: ".($tenant->domains()->where('type', 'admin')->value('host') ?: '(defina o endereço adm da marca)'));
        $this->warn("SENHA TEMPORÁRIA (aparece só agora): {$senha}");

        return self::SUCCESS;
    }
}
