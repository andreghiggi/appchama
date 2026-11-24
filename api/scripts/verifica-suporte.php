<?php
// Verifica o suporte (chamados do passageiro/motorista, resposta da equipe com aviso no celular, isolamento). Tudo dentro de transacao, desfeito no fim.
// Rodar: php artisan tinker scripts/verifica-suporte.php
use App\Domains\Support\Services\SupportService;
use App\Http\Controllers\Api\SupportTicketController;
use App\Models\{DeviceToken, SupportMessage, SupportTicket, Tenant, User};
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Http};

DB::beginTransaction();
$ok = 0; $falhas = [];
$check = function (string $nome, bool $cond, $extra = '') use (&$ok, &$falhas) {
    if ($cond) { $ok++; echo "  ok  $nome\n"; } else { $falhas[] = $nome; echo "  FALHOU  $nome ".(is_scalar($extra) ? $extra : json_encode($extra))."\n"; }
};
$http = function (callable $c) { try { return [200, $c()]; } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { return [$e->getStatusCode(), $e->getMessage()]; } catch (\Illuminate\Validation\ValidationException $e) { return [422, $e->errors()]; } };
try {
    $pass = User::withoutGlobalScopes()->where('role', 'passenger')->firstOrFail();
    $drv = User::withoutGlobalScopes()->where('role', 'driver')->where('tenant_id', $pass->tenant_id)->first() ?? User::withoutGlobalScopes()->where('role', 'driver')->firstOrFail();
    $staff = User::withoutGlobalScopes()->whereIn('role', ['admin', 'support'])->first() ?? $pass;
    app()->instance('currentTenantId', $pass->tenant_id);
    $svc = app(SupportService::class);
    $ctl = app(SupportTicketController::class);
    $req = fn (User $u, array $dados = []) => tap(Request::create('/x', 'POST', $dados), fn ($r) => $r->setUserResolver(fn () => $u));

    // --- abrir
    $t = $svc->open($pass, 'payment', 'Paguei no cartao e a corrida nao finalizou');
    $check('abre chamado aberto, com a 1a mensagem do usuario', $t->status === 'open' && $t->messages()->count() === 1 && $t->messages()->first()->author === 'user', $t->toArray());
    $check('equipe ve como novo; usuario nao tem pendencia', $t->staff_unread === true && $t->user_unread === false);
    $check('resumo traz o assunto e o texto', str_contains($t->subject, 'Pagamento') && str_contains($t->subject, 'cartao'), $t->subject);
    [$s] = $http(fn () => $svc->open($pass, 'xyz', 'texto qualquer'));
    $check('assunto invalido e recusado (422)', $s === 422);
    [$s] = $http(fn () => $svc->open($pass, 'ride', 'Problema na corrida', (string) \Illuminate\Support\Str::uuid()));
    $check('nao liga a uma corrida que nao e da pessoa', $s === 422);
    $r = $ctl->store($req($pass, ['category' => 'report', 'body' => 'Motorista foi grosseiro comigo']));
    $check('controller devolve 201 com a conversa', $r->getStatusCode() === 201 && count($r->getData(true)['messages']) === 1, $r->getData(true));
    [$s] = $http(fn () => $ctl->store($req($pass, ['category' => 'other', 'body' => 'a'])));
    $check('texto muito curto e recusado', $s === 422);

    // --- limite de chamados abertos
    $bloqueou = false;
    for ($i = 0; $i < 6; $i++) { [$s] = $http(fn () => $svc->open($pass, 'other', 'Chamado numero '.$i.' de teste')); if ($s === 422) { $bloqueou = true; break; } }
    $check('limita a '.SupportService::MAX_OPEN_PER_USER.' chamados abertos por pessoa', $bloqueou);

    // --- resposta da equipe + aviso no celular
    DeviceToken::withoutGlobalScopes()->create(['tenant_id' => $pass->tenant_id, 'user_id' => $pass->id, 'token' => 'ExponentPushToken[teste-suporte]', 'platform' => 'android']);
    Http::swap(new Factory()); Http::fake(['exp.host/*' => Http::response(['data' => [['status' => 'ok']]], 200)]);
    $svc->staffReply($staff, $t, 'Ja estamos verificando seu pagamento.');
    $t = $t->fresh();
    $check('resposta da equipe: em atendimento e usuario com aviso', $t->status === 'in_progress' && $t->user_unread === true && $t->staff_unread === false, $t->toArray());
    Http::assertSent(fn ($r) => str_contains($r->url(), 'exp.host') && ($r[0]['data']['ticket_id'] ?? null) === $t->id && ($r[0]['data']['type'] ?? null) === 'support_reply');
    $check('conversa tem 2 mensagens, a ultima da equipe', $t->messages()->count() === 2 && $t->messages()->get()->last()->author === 'staff');

    // --- usuario le (zera o aviso) e responde
    $r = $ctl->show($req($pass), $t);
    $check('abrir o chamado limpa o aviso do usuario', $r->getData(true)['unread'] === false && $t->fresh()->user_unread === false);
    $ctl->reply($req($pass, ['body' => 'Obrigado, aguardo.']), $t);
    $check('resposta do usuario volta para a equipe como nova', $t->fresh()->staff_unread === true && $t->messages()->count() === 3);

    // --- encerrar e reabrir
    $ctl->close($req($pass), $t);
    $check('usuario encerra o proprio chamado', $t->fresh()->status === 'closed');
    $ctl->reply($req($pass, ['body' => 'Voltou a acontecer']), $t);
    $check('mensagem em chamado encerrado reabre', $t->fresh()->status === 'open');

    // --- isolamento
    [$s] = $http(fn () => $ctl->show($req($drv), $t));
    $check('outra pessoa nao abre o chamado (404)', $s === 404);
    [$s] = $http(fn () => $ctl->reply($req($drv, ['body' => 'intruso']), $t));
    $check('outra pessoa nao responde no chamado (404)', $s === 404);
    [$s] = $http(fn () => $ctl->close($req($drv), $t));
    $check('outra pessoa nao encerra o chamado (404)', $s === 404);
    $check('lista mostra so os chamados da propria pessoa', collect($ctl->index($req($drv))->getData(true)['data'])->where('id', $t->id)->isEmpty());
    $outra = Tenant::query()->where('id', '!=', $pass->tenant_id)->first();
    if ($outra) {
        app()->instance('currentTenantId', $outra->id);
        $check('outra marca nao enxerga o chamado', SupportTicket::query()->where('id', $t->id)->doesntExist() && SupportMessage::query()->where('support_ticket_id', $t->id)->doesntExist());
        app()->instance('currentTenantId', $pass->tenant_id);
    } else { echo "  (so ha 1 marca neste banco: isolamento entre marcas nao exercitado)\n"; }

    // --- painel e rotas
    $check('painel: telas do chamado existem', class_exists(\App\Filament\Resources\SupportTickets\Pages\ViewSupportTicket::class) && class_exists(\App\Filament\Resources\SupportTickets\SupportTicketResource::class));
    $rotas = collect(app('router')->getRoutes())->map->uri()->filter(fn ($u) => str_contains($u, 'support/tickets'))->count();
    $check('5 rotas de suporte registradas', $rotas === 5, $rotas);
} catch (\Throwable $e) {
    $falhas[] = 'EXCECAO: '.$e->getMessage().' @ '.basename($e->getFile()).':'.$e->getLine();
    echo '  EXCECAO '.$e->getMessage().' @ '.$e->getFile().':'.$e->getLine()."\n";
} finally {
    DB::rollBack();
}
echo "\nSUPORTE: $ok ok, ".count($falhas)." falhas\n";
