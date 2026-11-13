<?php
namespace App\Domains\Payments\Services;
use App\Models\Driver;
use App\Models\Tenant;
use App\Models\WalletRecharge;
class DriverWalletRechargeService {
    public function __construct(private AsaasClient $client, private AsaasCustomerService $customers, private DriverWalletService $wallets) {}
    public function createPix(Driver $driver, Tenant $tenant, float $amount): array {
        abort_unless($amount >= 20, 422, 'A recarga mínima é de R$ 20,00.');
        abort_unless($this->client->isConfigured($tenant), 503, 'Gateway de pagamento não configurado.');
        $driver->loadMissing('user'); $customer = $this->customers->ensureForUser($driver->user, $tenant);
        $recharge = WalletRecharge::query()->create(['tenant_id'=>$tenant->id,'driver_id'=>$driver->user_id,'amount'=>round($amount,2),'status'=>'pending']);
        $payment = $this->client->post('/payments', ['customer'=>$customer,'billingType'=>'PIX','value'=>(float)$recharge->amount,'dueDate'=>now()->addDay()->format('Y-m-d'),'description'=>'Recarga carteira Chama #'.substr($recharge->id,0,8),'externalReference'=>'wallet-recharge:'.$recharge->id], $tenant);
        $id=$payment['id']??null; abort_unless($id,422,'Falha ao criar cobrança de recarga.');
        $pix=$this->client->get("/payments/{$id}/pixQrCode",$tenant); $recharge->update(['asaas_payment_id'=>$id,'payload'=>['payment'=>$payment,'pix'=>$pix]]);
        return ['recharge'=>$recharge->fresh(),'pix_qr_code'=>$pix['encodedImage']??null,'pix_copy_paste'=>$pix['payload']??null];
    }
    public function confirm(WalletRecharge $recharge): void {
        if ($recharge->status === 'paid') return;
        $recharge->update(['status'=>'paid']); $this->wallets->creditPrepaidRecharge($recharge->driver_id,(float)$recharge->amount,$recharge->id);
    }
}
