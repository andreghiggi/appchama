<?php

namespace App\Console\Commands;

use App\Domains\Payments\Services\DriverWithdrawalService;
use Illuminate\Console\Command;

class ReconcileWithdrawalsCommand extends Command
{
    protected $signature = 'withdrawals:reconcile {--minutes=5 : Idade mínima em minutos para consultar no Asaas}';

    protected $description = 'Atualiza saques presos em processing consultando status no Asaas';

    public function handle(DriverWithdrawalService $withdrawals): int
    {
        $minutes = max(0, (int) $this->option('minutes'));
        $stats = $withdrawals->reconcileStaleProcessing($minutes);

        $this->info(sprintf(
            'Reconciliação: %d verificados, %d concluídos, %d falharam.',
            $stats['checked'],
            $stats['updated'],
            $stats['failed'],
        ));

        return self::SUCCESS;
    }
}
