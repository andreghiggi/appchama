<?php
// Verifica o credito manual de comissao do painel admin (carteira do motorista). Tudo dentro de transacao, desfeito no fim.
// Rodar: php artisan tinker scripts/verifica-credito-manual.php
use App\Domains\Payments\Services\DriverWalletService;
use App\Models\{Driver, DriverWallet, DriverWalletTransaction, Tenant, User};
use Illuminate\Support\Facades\DB;

DB::beginTransaction();
$ok = 0; $falhas = [];
$check = function (string $nome, bool $cond, $extra = '') use (&$ok, &$falhas) {
    if ($cond) { $ok++; echo "  ok  $nome\n"; } else { $falhas[] = $nome; echo "  FALHOU  $nome ".(is_scalar($extra) ? $extra : json_encode($extra))."\n"; }
};
try {
    // qualquer motorista existente serve (tudo e desfeito no fim); a marca de referencia passa a ser a dele
    $driver = Driver::withoutGlobalScopes()->firstOrFail();
    $tenant = Tenant::query()->findOrFail($driver->tenant_id);
    $id = (string) $driver->user_id;
    $svc = app(DriverWalletService::class);
    $antes = (float) (DriverWallet::withoutGlobalScopes()->where('driver_id', $id)->value('prepaid_balance') ?? 0);

    // simula o admin vendo OUTRA marca no painel: a carteira do motorista tem de ser achada mesmo assim
    $outra = Tenant::query()->where('id', '!=', $tenant->id)->first();
    if ($outra) { app()->instance('currentTenantId', $outra->id); }

    $w = $svc->creditManual($id, 25.50, 'teste', 'Admin Teste');
    $check('credito soma ao saldo de comissao', abs((float) $w->prepaid_balance - ($antes + 25.50)) < 0.001, [$antes, $w->prepaid_balance]);
    $check('nao criou uma 2a carteira para o motorista', DriverWallet::withoutGlobalScopes()->where('driver_id', $id)->count() === 1);
    $tx = DriverWalletTransaction::withoutGlobalScopes()->where('driver_id', $id)->where('type', 'manual_credit')->latest('created_at')->first();
    $check('extrato registra tipo, valor, motivo e quem fez', $tx && (float) $tx->amount === 25.5 && str_contains((string) $tx->description, 'Admin Teste') && str_contains((string) $tx->description, 'teste'), $tx);
    $check('extrato e a carteira ficam na marca do motorista', $tx && $tx->tenant_id === $tenant->id && DriverWallet::withoutGlobalScopes()->where('driver_id', $id)->value('tenant_id') === $tenant->id);

    foreach ([0, -5, 5000.01] as $ruim) {
        $barrou = false;
        try { $svc->creditManual($id, $ruim, null, null); } catch (\Throwable $e) { $barrou = true; }
        $check("valor invalido ($ruim) e recusado", $barrou);
    }
    app()->forgetInstance('currentTenantId');

    // quem tem saldo suficiente passa na regra de aceitar corrida
    $svc->creditManual($id, 100, null, null);
    $passou = true;
    try { $svc->assertCanAcceptRide($driver, 14.50, 15.0); } catch (\Throwable $e) { $passou = false; }
    $check('com credito o motorista volta a poder aceitar corrida', $passou);
} catch (\Throwable $e) {
    $check('execucao sem excecao', false, $e->getMessage());
} finally {
    DB::rollBack();
}
echo "\nCREDITO MANUAL: $ok ok, ".count($falhas)." falhas\n";
