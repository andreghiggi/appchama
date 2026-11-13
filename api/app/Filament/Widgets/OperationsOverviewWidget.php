<?php

namespace App\Filament\Widgets;

use App\Models\Ride;
use App\Models\Scopes\TenantScope;
use App\Support\StatusLabels;
use Filament\Widgets\Concerns\CanPoll;
use Filament\Widgets\Widget;

class OperationsOverviewWidget extends Widget
{
    use CanPoll;

    protected static ?int $sort = 3;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.operations-overview';

    protected function getViewData(): array
    {
        $activeRides = Ride::query()
            ->with(['passenger:id,name,phone', 'driver.user:id,name,phone', 'driver.city:id,name', 'city:id,name'])
            ->whereIn('status', ['accepted', 'driver_arrived', 'in_progress'])
            ->whereNotNull('driver_id')
            ->latest('requested_at')
            ->limit(20)
            ->get()
            ->map(fn (Ride $ride) => [
                'code' => strtoupper(substr($ride->id, 0, 8)),
                'passenger' => $ride->passenger?->name ?? '—',
                'passenger_phone' => $this->formatPhone($ride->passenger?->phone),
                'driver' => $ride->driver?->user?->name ?? '—',
                'driver_phone' => $this->formatPhone($ride->driver?->user?->phone),
                'city' => $ride->city?->name ?? $ride->driver?->city?->name ?? '—',
                'status' => StatusLabels::rideStatus($ride->status),
                'fare' => $ride->estimated_fare
                    ? 'R$ '.number_format((float) $ride->estimated_fare, 2, ',', '.')
                    : '—',
            ])
            ->all();

        $driversInRide = Ride::query()
            ->with(['driver.user:id,name,phone', 'driver.city:id,name', 'passenger:id,name'])
            ->whereIn('status', ['accepted', 'driver_arrived', 'in_progress'])
            ->whereNotNull('driver_id')
            ->latest('requested_at')
            ->limit(20)
            ->get()
            ->map(fn (Ride $ride) => [
                'name' => $ride->driver?->user?->name ?? 'Motorista',
                'phone' => $this->formatPhone($ride->driver?->user?->phone),
                'city' => $ride->driver?->city?->name ?? '—',
                'passenger' => $ride->passenger?->name ?? '—',
                'status' => StatusLabels::rideStatus($ride->status),
            ])
            ->all();

        $awaitingPayment = Ride::query()
            ->with(['passenger:id,name,phone', 'driver.user:id,name,phone', 'city:id,name'])
            ->where('status', 'awaiting_payment')
            ->latest('updated_at')
            ->limit(10)
            ->get()
            ->map(fn (Ride $ride) => [
                'code' => strtoupper(substr($ride->id, 0, 8)),
                'passenger' => $ride->passenger?->name ?? '—',
                'driver' => $ride->driver?->user?->name ?? '—',
                'fare' => $ride->final_fare
                    ? 'R$ '.number_format((float) $ride->final_fare, 2, ',', '.')
                    : '—',
                'net' => $ride->driver_net_amount
                    ? 'R$ '.number_format((float) $ride->driver_net_amount, 2, ',', '.')
                    : '—',
            ])
            ->all();

        return [
            'driversInRide' => $driversInRide,
            'activeRides' => $activeRides,
            'awaitingPayment' => $awaitingPayment,
        ];
    }

    private function formatPhone(?string $phone): string
    {
        if (! $phone) {
            return '—';
        }

        $digits = preg_replace('/\D/', '', $phone) ?? $phone;

        if (strlen($digits) === 13) {
            return sprintf(
                '+%s (%s) %s-%s',
                substr($digits, 0, 2),
                substr($digits, 2, 2),
                substr($digits, 4, 5),
                substr($digits, 9),
            );
        }

        return $phone;
    }

    protected function getPollingInterval(): ?string
    {
        return '15s';
    }
}
