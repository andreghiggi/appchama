<?php

namespace App\Domains\Payments\Services;

use App\Models\Tenant;

class CommissionCalculator
{
    /**
     * @return array{gross: float, platform_fee: float, net: float}
     */
    public function split(float $gross, Tenant $tenant): array
    {
        $pct = max(0, min(100, (float) ($tenant->platform_commission_pct ?? 0)));
        $platformFee = round($gross * ($pct / 100), 2);
        $net = round($gross - $platformFee, 2);

        return [
            'gross' => round($gross, 2),
            'platform_fee' => $platformFee,
            'net' => $net,
        ];
    }
}
