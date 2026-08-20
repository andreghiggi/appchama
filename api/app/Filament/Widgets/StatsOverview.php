<?php

namespace App\Filament\Widgets;

use App\Models\Driver;
use App\Models\Ride;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $ridesToday = Ride::query()->whereDate('requested_at', today())->count();
        $driversOnline = Driver::query()->where('online', true)->count();
        $revenueToday = Ride::query()
            ->whereDate('completed_at', today())
            ->where('status', 'completed')
            ->sum('final_fare');

        return [
            Stat::make('Corridas hoje', (string) $ridesToday)
                ->description('Solicitações do dia')
                ->color('primary'),
            Stat::make('Motoristas online', (string) $driversOnline)
                ->description('Disponíveis agora')
                ->color('success'),
            Stat::make('Receita do dia', 'R$ '.number_format((float) $revenueToday, 2, ',', '.'))
                ->description('Corridas concluídas')
                ->color('warning'),
            Stat::make('Taxa aceitação', '—')
                ->description('Meta: 90%')
                ->color('info'),
        ];
    }
}
