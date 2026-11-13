<?php

namespace App\Filament\Widgets;

use App\Models\DriverWallet;
use App\Models\RidePayment;
use App\Models\Scopes\TenantScope;
use Filament\Widgets\Concerns\CanPoll;
use Filament\Widgets\Widget;

class FinanceHealthWidget extends Widget
{
    use CanPoll;

    protected static ?int $sort = 4;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.finance-health';

    protected function getViewData(): array
    {
        $negativeWallets = DriverWallet::query()
            ->with('driver.user:id,name,phone')
            ->where('prepaid_balance', '<', 0)
            ->orderBy('prepaid_balance')
            ->limit(20)
            ->get()
            ->map(fn (DriverWallet $wallet) => [
                'name' => $wallet->driver?->user?->name ?? '—',
                'phone' => $wallet->driver?->user?->phone ?? '—',
                'balance' => 'R$ '.number_format((float) $wallet->prepaid_balance, 2, ',', '.'),
            ])
            ->all();

        $pendingPayments = RidePayment::query()
            ->with(['ride.passenger:id,name', 'ride.driver.user:id,name'])
            ->where('status', 'pending')
            ->whereNotNull('asaas_payment_id')
            ->orderBy('created_at')
            ->limit(20)
            ->get()
            ->map(function (RidePayment $payment) {
                $ageDays = (int) $payment->created_at->diffInDays(now());

                return [
                    'code' => strtoupper(substr($payment->id, 0, 8)),
                    'method' => strtoupper($payment->method),
                    'passenger' => $payment->ride?->passenger?->name ?? '—',
                    'driver' => $payment->ride?->driver?->user?->name ?? '—',
                    'age_days' => $ageDays,
                    'expiring_soon' => $ageDays >= 2,
                ];
            })
            ->all();

        return [
            'negativeWallets' => $negativeWallets,
            'pendingPayments' => $pendingPayments,
        ];
    }

    protected function getPollingInterval(): ?string
    {
        return '30s';
    }
}
