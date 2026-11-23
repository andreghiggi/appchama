<?php
// Verifica que o passageiro so precisa informar o que falta para pagar (Pix: nome completo + CPF; cartao: + e-mail e endereco).
// Rodar: php artisan tinker scripts/verifica-perfil-pagamento.php   (dentro de transacao, desfeito no fim)
use App\Http\Controllers\Api\ProfileController;
use App\Models\{Tenant, User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

DB::beginTransaction();
$ok = 0; $falhas = [];
$check = function (string $nome, bool $cond, $extra = '') use (&$ok, &$falhas) {
    if ($cond) { $ok++; echo "  ok  $nome\n"; } else { $falhas[] = $nome; echo "  FALHOU  $nome ".(is_scalar($extra) ? $extra : json_encode($extra))."\n"; }
};
try {
    $tenant = Tenant::query()->firstOrFail();
    $mk = fn (array $o) => User::withoutGlobalScopes()->forceCreate($o + ['tenant_id' => $tenant->id, 'role' => 'passenger', 'status' => 'active', 'phone' => '5190001'.random_int(1000, 9999)]);

    $u = $mk(['name' => 'Maria Silva', 'cpf' => '52998224725']);
    $check('so nome completo + CPF ja basta para o Pix', $u->hasPixProfile() && $u->missingPaymentFields('pix') === []);
    $check('para o cartao falta e-mail e endereco (so isso)', $u->missingPaymentFields('credit_card') === ['email', 'home_address', 'home_address_number', 'home_postal_code', 'home_city', 'home_state'], $u->missingPaymentFields('credit_card'));

    $u2 = $mk(['name' => 'Maria', 'cpf' => null]);
    $check('nome de uma palavra e sem CPF: pede os dois', $u2->missingPaymentFields('pix') === ['name', 'cpf'], $u2->missingPaymentFields('pix'));
    $u3 = $mk(['name' => 'Joao Souza', 'cpf' => '11111111111']);
    $check('CPF invalido conta como faltando', $u3->missingPaymentFields('pix') === ['cpf']);

    $completo = $mk(['name' => 'Ana Lima', 'cpf' => '52998224725', 'email' => 'ana@teste.com', 'home_address' => 'Rua A', 'home_address_number' => '10', 'home_postal_code' => '99200000', 'home_city' => 'Guapore', 'home_state' => 'RS']);
    $check('cadastro completo: nada falta nem no cartao', $completo->hasPaymentProfile() && $completo->missingPaymentFields('credit_card') === []);

    // atualizacao parcial pelo app: manda so o CPF
    $c = app(ProfileController::class);
    $req = Request::create('/profile', 'PUT', ['cpf' => '52998224725']); $req->setUserResolver(fn () => $u2);
    $r = $c->update($req, app(\App\Domains\Payments\Services\AsaasCustomerService::class));
    $depois = $u2->fresh();
    $check('enviar so o CPF atualiza so o CPF (nao exige o resto)', $r->getStatusCode() === 200 && $depois->cpf === '52998224725' && $depois->name === 'Maria', $depois->only(['name', 'cpf']));
    $barrou = false;
    try { $req = Request::create('/profile', 'PUT', ['cpf' => '11111111111']); $req->setUserResolver(fn () => $u2); $c->update($req, app(\App\Domains\Payments\Services\AsaasCustomerService::class)); } catch (\Throwable $e) { $barrou = true; }
    $check('CPF invalido continua sendo recusado', $barrou);
} catch (\Throwable $e) {
    $check('execucao sem excecao', false, $e->getMessage());
} finally {
    DB::rollBack();
}
echo "\nPERFIL PAGAMENTO: $ok ok, ".count($falhas)." falhas\n";
