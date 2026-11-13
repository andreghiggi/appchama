<?php

namespace App\Console\Commands;

use App\Models\RidePayment;
use Illuminate\Console\Command;

class ExpireStaleRidePaymentsCommand extends Command
{
    protected $signature = 'payments:expire-stale {--days=3 : Idade mínima em dias}';

    protected $description = 'Marca como cancelados pagamentos Pix/cartão pendentes há muito tempo (nunca confirmados pelo Asaas)';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));

        $count = RidePayment::query()
            ->where('status', 'pending')
            ->whereNotNull('asaas_payment_id')
            ->where('created_at', '<=', now()->subDays($days))
            ->update(['status' => 'cancelled']);

        $this->info("Pagamentos expirados: {$count}.");

        return self::SUCCESS;
    }
}
