<?php

namespace App\Console\Commands;

use App\Domains\Payments\Services\RidePaymentReconcileService;
use Illuminate\Console\Command;

class ReconcileRidePaymentsCommand extends Command
{
    protected $signature = 'payments:reconcile-pending {--minutes=2 : Idade mínima em minutos}';

    protected $description = 'Confirma pagamentos Pix/cartão pendentes consultando o Asaas';

    public function handle(RidePaymentReconcileService $reconcile): int
    {
        $minutes = max(0, (int) $this->option('minutes'));
        $stats = $reconcile->reconcilePending($minutes);

        $this->info(sprintf(
            'Pagamentos: %d verificados, %d confirmados, %d ignorados.',
            $stats['checked'],
            $stats['confirmed'],
            $stats['skipped'],
        ));

        return self::SUCCESS;
    }
}
