<?php

namespace App\Filament\Widgets;

use App\Models\Driver;
use App\Models\Ride;
use App\Models\Scopes\TenantScope;
use Filament\Widgets\Concerns\CanPoll;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends StatsOverviewWidget
{
    use CanPoll;

    protected static ?int $sort = 1;

    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $ridesToday = Ride::query()
            ->whereDate('requested_at', today())
            ->count();

        $activeRides = Ride::query()
            ->whereIn('status', ['searching', 'accepted', 'driver_arrived', 'in_progress', 'awaiting_payment'])
            ->count();

        $driversOnline = Driver::query()->where('online', true)->count();
        $driversTotal = Driver::query()->count();

        $revenueToday = Ride::query()
            ->whereDate('paid_at', today())
            ->where('payment_status', 'paid')
            ->sum('platform_fee_amount');

        $grossToday = Ride::query()
            ->whereDate('paid_at', today())
            ->where('payment_status', 'paid')
            ->sum('final_fare');

        return [
            Stat::make('Corridas hoje', (string) $ridesToday)
                ->description('Solicitações do dia')
                ->color('primary'),
            Stat::make('Corridas ativas', (string) $activeRides)
                ->description('Em andamento agora')
                ->color('warning'),
            Stat::make('Motoristas online', (string) $driversOnline)
                ->description("De {$driversTotal} cadastrados")
                ->color('success'),
            Stat::make('Comissão do dia', 'R$ '.number_format((float) $revenueToday, 2, ',', '.'))
                ->description('R$ '.number_format((float) $grossToday, 2, ',', '.').' bruto')
                ->color('info'),
        ];
    }

    protected function getPollingInterval(): ?string
    {
        return '15s';
    }
}
