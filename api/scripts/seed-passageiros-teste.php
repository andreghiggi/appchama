<?php
// Cria 10 passageiros FICTICIOS ja validados (status ativo) numa marca, para testes. Pode rodar de novo (nao duplica).
// Rodar: php artisan tinker scripts/seed-passageiros-teste.php          (marca vaide55)
// Para entrar com eles no app sem SMS real, os telefones precisam estar em OTP_TEST_ACCOUNTS (.env) com o codigo fixo de cada um.
use App\Models\{Tenant, User};

$slug = getenv('MARCA') ?: 'vaide55';
$tenant = Tenant::query()->where('slug', $slug)->firstOrFail();
$nomes = ['Ana Beatriz Teste', 'Bruno Teste', 'Carla Teste', 'Diego Teste', 'Eduarda Teste', 'Fabio Teste', 'Gabriela Teste', 'Henrique Teste', 'Isabela Teste', 'Joao Teste'];
$contas = [];
foreach ($nomes as $i => $nome) {
    $n = $i + 1;
    $telefone = '5190000'.str_pad((string) $n, 4, '0', STR_PAD_LEFT); // 51 9 0000 00NN: 11 digitos, numero ficticio
    $codigo = '5000'.str_pad((string) $n, 2, '0', STR_PAD_LEFT);
    $u = User::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('phone', $telefone)->first()
        ?? User::withoutGlobalScopes()->forceCreate([
            'tenant_id' => $tenant->id, 'name' => $nome, 'phone' => $telefone, 'role' => 'passenger', 'status' => 'active',
            'home_city' => 'Encantado', 'home_state' => 'RS',
        ]);
    $contas[] = $telefone.':'.$codigo;
    echo sprintf("  %-18s telefone %s  codigo %s\n", $u->name, $telefone, $codigo);
}
echo "\nOTP_TEST_ACCOUNTS (acrescentar ao .env, separado por virgula):\n".implode(',', $contas)."\n";
