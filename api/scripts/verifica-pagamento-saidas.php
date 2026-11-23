<?php
// Verifica as saidas do pagamento da corrida: pagar direto ao motorista, aviso de modo de teste e conferencia do cartao.
// Rodar: php artisan tinker scripts/verifica-pagamento-saidas.php   (dentro de transacao, desfeito no fim)
use App\Domains\Payments\Services\{AsaasClient, RidePaymentReconcileService, RidePaymentService};
use App\Models\{Ride, RidePayment, Tenant};
use Illuminate\Support\Facades\DB;

DB::beginTransaction();
$ok = 0; $falhas = [];
$check = function (string $nome, bool $cond, $extra = '') use (&$ok, &$falhas) {
    if ($cond) { $ok++; echo "  ok  $nome\n"; } else { $falhas[] = $nome; echo "  FALHOU  $nome ".(is_scalar($extra) ? $extra : json_encode($extra))."\n"; }
};
try {
    $base = Ride::withoutGlobalScopes()->whereNotNull('driver_id')->firstOrFail();
    $tenant = Tenant::query()->findOrFail($base->tenant_id);
    $tenant->forceFill(['asaas_environment' => 'sandbox', 'payment_methods_enabled' => ['pix' => true, 'credit_card' => true, 'direct_driver' => true]])->save();

    $nova = function () use ($base) {
        $r = $base->replicate();
        $r->forceFill(['status' => 'awaiting_payment', 'payment_status' => 'pending', 'payment_method' => null, 'final_fare' => 32.91, 'driver_net_amount' => 27.97]);
        $r->save();
        return $r;
    };
    $mkPay = fn (Ride $r, array $o) => RidePayment::withoutGlobalScopes()->forceCreate($o + ['tenant_id' => $r->tenant_id, 'ride_id' => $r->id, 'amount' => 32.91, 'status' => 'pending']);
    $svc = app(RidePaymentService::class);

    echo "== Pagar direto ao motorista\n";
    $r1 = $nova();
    $pix = $mkPay($r1, ['method' => 'pix', 'asaas_payment_id' => 'pay_teste_pix']);
    $st = $svc->paymentStatus($r1);
    $check('status avisa modo de teste (sandbox)', ($st['test_mode'] ?? null) === true);
    $check('pagamento direto esta habilitado no status', ($st['methods_enabled']['direct_driver'] ?? false) === true);
    $svc->choosePaymentMethod($r1, 'direct_driver');
    $check('escolher direto marca a corrida e cancela o Pix pendente', $r1->fresh()->payment_method === 'direct_driver' && $pix->fresh()->status === 'cancelled');
    $svc->confirmDirectPayment($r1->fresh());
    $check('motorista confirma e a corrida fica paga', $r1->fresh()->payment_status === 'paid');

    echo "== Conferencia do cartao (checkout)\n";
    $r2 = $nova();
    $cartao = $mkPay($r2, ['method' => 'credit_card', 'asaas_checkout_id' => 'chk-teste', 'created_at' => now()->subMinutes(10)]);
    $falso = function (string $statusCobranca) {
        return new class($statusCobranca) extends AsaasClient {
            public function __construct(private string $s) {}
            public function isConfigured(?Tenant $tenant = null): bool { return true; }
            public function get(string $path, ?Tenant $tenant = null): array {
                return str_contains($path, 'checkoutSession=chk-teste') ? ['data' => [['id' => 'pay_cartao', 'status' => $this->s, 'billingType' => 'CREDIT_CARD']]] : [];
            }
        };
    };
    app()->instance(AsaasClient::class, $falso('PENDING'));
    app()->forgetInstance(RidePaymentReconcileService::class); app()->forgetInstance(RidePaymentService::class);
    app(RidePaymentReconcileService::class)->reconcilePending(2);
    $check('cobranca ainda pendente no Asaas: corrida segue aguardando', $r2->fresh()->payment_status === 'pending');
    app()->instance(AsaasClient::class, $falso('CONFIRMED'));
    app()->forgetInstance(RidePaymentReconcileService::class); app()->forgetInstance(RidePaymentService::class);
    app(RidePaymentReconcileService::class)->reconcilePending(2);
    $check('cobranca CONFIRMADA no Asaas: a rotina marca a corrida como paga sozinha', $r2->fresh()->payment_status === 'paid' && $cartao->fresh()->status === 'paid', [$r2->fresh()->payment_status, $cartao->fresh()->status]);
} catch (\Throwable $e) {
    $check('execucao sem excecao', false, $e->getMessage().' @'.$e->getLine());
} finally {
    DB::rollBack();
}
echo "\nPAGAMENTO SAIDAS: $ok ok, ".count($falhas)." falhas\n";
