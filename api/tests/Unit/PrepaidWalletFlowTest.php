<?php
namespace Tests\Unit;
use App\Domains\Payments\Services\DriverWalletService;
use App\Models\Driver;
use App\Models\Ride;
use App\Models\WalletRecharge;
use App\Domains\Payments\Services\AsaasClient;
use App\Domains\Payments\Services\AsaasCustomerService;
use App\Domains\Payments\Services\DriverWalletRechargeService;
use App\Domains\Payments\Services\AsaasPaymentService;
use App\Domains\Payments\Services\RidePaymentService;
use Illuminate\Database\Schema\Blueprint;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
class PrepaidWalletFlowTest extends TestCase
{
    private DriverWalletService $wallets;
    private Driver $driver;
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('driver_wallets', function (Blueprint $table): void {
            $table->string('driver_id')->primary();
            $table->decimal('balance_available', 12, 2)->default(0);
            $table->decimal('prepaid_balance', 12, 2)->default(0);
            $table->decimal('total_earnings', 12, 2)->default(0);
            $table->timestamps();
        });
        Schema::create('wallet_recharges', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('tenant_id')->nullable();
            $table->string('driver_id');
            $table->decimal('amount', 10, 2);
            $table->string('status');
            $table->string('asaas_payment_id')->nullable();
            $table->text('payload')->nullable();
            $table->timestamps();
        });
        Schema::create('driver_wallet_transactions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('driver_id');
            $table->string('type');
            $table->decimal('amount', 10, 2);
            $table->decimal('balance_after', 12, 2);
            $table->decimal('prepaid_balance_after', 12, 2)->nullable();
            $table->string('ride_id')->nullable();
            $table->string('withdrawal_id')->nullable();
            $table->string('description')->nullable();
            $table->timestamp('created_at')->nullable();
        });
        $this->wallets = new DriverWalletService();
        $this->driver = new Driver(['user_id' => 'driver-test-1']);
    }
    protected function tearDown(): void
    {
        Schema::dropIfExists('driver_wallet_transactions');
        Schema::dropIfExists('wallet_recharges');
        Schema::dropIfExists('driver_wallets');
        parent::tearDown();
    }
    public function test_acceptance_is_rejected_when_prepaid_balance_cannot_cover_estimated_commission(): void
    {
        $wallet = $this->wallets->ensureWallet($this->driver);
        $wallet->update(['prepaid_balance' => 14.99]);
        $this->expectException(HttpException::class);
        $this->wallets->assertCanAcceptRide($this->driver, 100, 15);
    }
    public function test_acceptance_is_allowed_when_prepaid_balance_covers_estimated_commission(): void
    {
        $wallet = $this->wallets->ensureWallet($this->driver);
        $wallet->update(['prepaid_balance' => 15]);
        $this->wallets->assertCanAcceptRide($this->driver, 100, 15);
        $this->assertTrue(true);
    }

    public function test_confirmed_recharge_credits_once_even_when_webhook_is_replayed(): void
    {
        $recharge = WalletRecharge::query()->create([
            'id' => 'recharge-test-1', 'driver_id' => $this->driver->user_id,
            'amount' => 20, 'status' => 'pending',
        ]);
        $service = new DriverWalletRechargeService(
            $this->createMock(AsaasClient::class),
            $this->createMock(AsaasCustomerService::class),
            $this->wallets,
        );
        $service->confirm($recharge);
        $service->confirm($recharge->fresh());
        $this->assertSame(20.0, (float) $this->wallets->ensureWallet($this->driver)->fresh()->prepaid_balance);
        $this->assertSame(1, $this->wallets->ensureWallet($this->driver)->transactions()->where('type', 'wallet_recharge')->count());
    }


    public function test_failed_withdrawal_refund_is_idempotent(): void
    {
        $this->wallets->ensureWallet($this->driver);
        $this->wallets->refundFailedWithdrawal($this->driver->user_id, 12.50, 'withdrawal-test-1', 'Falha simulada');
        $this->wallets->refundFailedWithdrawal($this->driver->user_id, 12.50, 'withdrawal-test-1', 'Replay');
        $wallet = $this->wallets->ensureWallet($this->driver)->fresh();
        $this->assertSame(12.5, (float) $wallet->balance_available);
        $this->assertSame(1, $this->wallets->ensureWallet($this->driver)->transactions()->where('type', 'withdrawal_refund')->count());
    }
    public function test_already_paid_app_payment_confirmation_is_ignored(): void
    {
        $ride = new Ride(['id' => 'ride-paid-1', 'payment_status' => 'paid']);
        $service = new RidePaymentService(
            $this->createMock(AsaasPaymentService::class),
            $this->wallets,
        );
        $this->assertSame($ride, $service->handleAsaasPaymentConfirmed($ride, 'asaas-payment-1', 'pix'));
    }


    public function test_app_payment_adds_net_earnings_to_withdrawable_balance(): void
    {
        $ride = new Ride(['id' => 'ride-app-1', 'driver_id' => $this->driver->user_id, 'driver_net_amount' => 85]);
        $ride->setRelation('driver', $this->driver);
        $wallet = $this->wallets->creditFromRide($ride, true);
        $this->assertSame(85.0, (float) $wallet->balance_available);
        $this->assertSame(85.0, (float) $wallet->total_earnings);
        $this->assertSame(0.0, (float) $wallet->prepaid_balance);
    }
    public function test_direct_payment_records_earnings_without_increasing_withdrawable_balance(): void
    {
        $ride = new Ride(['id' => 'ride-direct-1', 'driver_id' => $this->driver->user_id, 'driver_net_amount' => 85]);
        $ride->setRelation('driver', $this->driver);
        $wallet = $this->wallets->creditFromRide($ride, false);
        $this->assertSame(0.0, (float) $wallet->balance_available);
        $this->assertSame(85.0, (float) $wallet->total_earnings);
        $this->assertStringContainsString('pagamento direto', (string) $this->wallets->ensureWallet($this->driver)->transactions()->latest('created_at')->value('description'));
    }

    public function test_commission_debit_succeeds_and_goes_negative_when_balance_is_insufficient(): void
    {
        // Regressão do bug corrigido em 2026-10-05: o débito de comissão não pode
        // abortar nesse ponto (a corrida já foi paga pelo passageiro) — se abortasse,
        // a transação que marca a corrida como paga seria desfeita inteira.
        $wallet = $this->wallets->ensureWallet($this->driver);
        $wallet->update(['prepaid_balance' => 5]);
        $ride = new Ride([
            'id' => 'ride-test-insufficient-1',
            'driver_id' => $this->driver->user_id,
            'platform_fee_amount' => 15,
        ]);
        $ride->setRelation('driver', $this->driver);

        $this->wallets->debitCommissionForRide($ride);

        $this->assertSame(-10.0, (float) $wallet->fresh()->prepaid_balance);
        $this->assertSame(1, $this->wallets->ensureWallet($this->driver)->transactions()->where('type', 'commission_debit')->count());
    }

    public function test_commission_debit_is_idempotent_for_the_same_ride(): void
    {
        $wallet = $this->wallets->ensureWallet($this->driver);
        $wallet->update(['prepaid_balance' => 20]);
        $ride = new Ride([
            'id' => 'ride-test-1',
            'driver_id' => $this->driver->user_id,
            'platform_fee_amount' => 15,
        ]);
        $ride->setRelation('driver', $this->driver);
        $this->wallets->debitCommissionForRide($ride);
        $this->wallets->debitCommissionForRide($ride);
        $this->assertSame(5.0, (float) $wallet->fresh()->prepaid_balance);
        $this->assertSame(1, $this->wallets->ensureWallet($this->driver)->transactions()->where('type', 'commission_debit')->count());
    }
}
