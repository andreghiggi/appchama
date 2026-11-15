<?php

namespace Tests\Unit;

use Tests\TestCase;

class AppVersionConfigTest extends TestCase
{
    public function test_le_versoes_por_marca_do_env(): void
    {
        config(['app_versions.tenants' => json_decode('{"vaide55":{"passenger":{"latest":9,"minimum":8},"driver":{"latest":9}}}', true)]);

        $this->assertSame(9, config('app_versions.tenants.vaide55.passenger.latest'));
        $this->assertSame(8, config('app_versions.tenants.vaide55.passenger.minimum'));
        $this->assertNull(config('app_versions.tenants.vaide55.driver.minimum'));
        $this->assertSame([], config('app_versions.tenants.outra.passenger', []));
    }
}
