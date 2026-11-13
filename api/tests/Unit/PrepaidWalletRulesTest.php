<?php
namespace Tests\Unit;
use App\Domains\Payments\Services\AsaasClient;
use App\Domains\Payments\Services\CommissionCalculator;
use App\Domains\Payments\Services\DriverWalletRechargeService;
use App\Domains\Payments\Services\DriverWalletService;
use App\Models\Driver;
use App\Models\Tenant;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;
class PrepaidWalletRulesTest extends TestCase
{
    public function test_default_commission_split_is_fifteen_percent_when_tenant_is_configured(): void
    {
        $tenant = new Tenant(['platform_commission_pct' => 15]);
        $split = (new CommissionCalculator())->split(100, $tenant);
        $this->assertSame(15.0, $split['platform_fee']);
        $this->assertSame(85.0, $split['net']);
    }
    public function test_recharge_below_twenty_is_rejected_before_gateway_call(): void
    {
        $service = new DriverWalletRechargeService(
            $this->createMock(AsaasClient::class),
            $this->createMock(\App\Domains\Payments\Services\AsaasCustomerService::class),
            $this->createMock(DriverWalletService::class),
        );
        $this->expectException(HttpException::class);
        $service->createPix(new Driver(), new Tenant(), 19.99);
    }
}
