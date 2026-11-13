<?php

namespace App\Domains\Payments\Services;

use App\Models\Driver;
use App\Models\Ride;

class EarningsReportService
{
    /**
     * Ganhos líquidos do motorista agrupados por semana (corridas pagas e concluídas).
     *
     * @return list<array{week_start: string, week_label: string, rides_count: int, total_earnings: float}>
     */
    public function weeklyReport(Driver $driver, int $weeks = 12): array
    {
        $since = now()->subWeeks($weeks - 1)->startOfWeek();

        $rides = Ride::query()
            ->where('driver_id', $driver->user_id)
            ->where('status', 'completed')
            ->where('payment_status', 'paid')
            ->where('paid_at', '>=', $since)
            ->get(['paid_at', 'driver_net_amount', 'final_fare']);

        $grouped = $rides->groupBy(fn (Ride $ride) => $ride->paid_at->startOfWeek()->format('Y-m-d'));

        $report = [];

        for ($i = 0; $i < $weeks; $i++) {
            $weekStart = now()->subWeeks($i)->startOfWeek();
            $key = $weekStart->format('Y-m-d');
            $weekRides = $grouped->get($key, collect());

            $report[] = [
                'week_start' => $key,
                'week_label' => $weekStart->format('d/m').' a '.$weekStart->copy()->endOfWeek()->format('d/m'),
                'rides_count' => $weekRides->count(),
                'total_earnings' => round(
                    $weekRides->sum(fn (Ride $ride) => (float) ($ride->driver_net_amount ?? $ride->final_fare ?? 0)),
                    2,
                ),
            ];
        }

        return $report;
    }
}
