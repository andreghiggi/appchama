<?php

namespace App\Support;

class StatusLabels
{
    public static function rideStatus(?string $status): string
    {
        return match ($status) {
            'requested' => 'Solicitada',
            'searching' => 'Buscando motorista',
            'accepted' => 'Aceita',
            'driver_arrived' => 'Motorista chegou',
            'in_progress' => 'Em andamento',
            'awaiting_payment' => 'Aguardando pagamento',
            'completed' => 'Concluída',
            'canceled_by_passenger' => 'Cancelada (passageiro)',
            'canceled_by_driver' => 'Cancelada (motorista)',
            'no_drivers_available' => 'Sem motoristas',
            default => $status ?? '—',
        };
    }

    public static function backgroundCheck(?string $status): string
    {
        return match ($status) {
            'pending' => 'Pendente',
            'approved' => 'Aprovado',
            'rejected' => 'Rejeitado',
            default => $status ?? '—',
        };
    }

    public static function subscription(?string $status): string
    {
        return match ($status) {
            'active' => 'Ativa',
            'pending' => 'Pendente',
            'overdue' => 'Em atraso',
            'suspended' => 'Suspensa',
            default => $status ?? '—',
        };
    }
}
