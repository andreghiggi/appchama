<x-filament-widgets::widget
    :attributes="
        (new \Filament\Support\View\ComponentAttributeBag)
            ->merge([
                'wire:poll.' . $this->getPollingInterval() => $this->getPollingInterval() ? true : null,
            ], escape: false)
    "
>
    <x-filament::section heading="Saúde financeira">
        <div class="grid gap-6 xl:grid-cols-2">
            <div>
                <h3 class="mb-3 text-sm font-semibold" style="color: var(--fi-body-text-color, inherit);">
                    Carteiras negativas ({{ count($negativeWallets) }})
                </h3>

                @if (count($negativeWallets) === 0)
                    <div class="rounded-xl p-4 text-sm" style="background: var(--fi-section-bg, rgba(128,128,128,.08)); color: var(--fi-body-text-color, inherit); opacity: .75;">
                        Nenhum motorista com saldo negativo.
                    </div>
                @else
                    <div class="space-y-3">
                        @foreach ($negativeWallets as $wallet)
                            <div
                                class="rounded-xl p-4"
                                style="border: 1px solid rgba(128,128,128,.25); background: var(--fi-section-bg, transparent);"
                            >
                                <div class="mb-2 flex items-center justify-between gap-3">
                                    <span class="text-base font-semibold" style="color: var(--fi-body-text-color, inherit);">
                                        {{ $wallet['name'] }}
                                    </span>
                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium" style="background: rgba(220,38,38,.15); color: #dc2626;">
                                        {{ $wallet['balance'] }}
                                    </span>
                                </div>
                                <div class="text-sm" style="color: var(--fi-body-text-color, inherit); opacity: .9;">
                                    <span style="opacity:.65;">Telefone:</span> {{ $wallet['phone'] }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div>
                <h3 class="mb-3 text-sm font-semibold" style="color: var(--fi-body-text-color, inherit);">
                    Pagamentos pendentes ({{ count($pendingPayments) }})
                </h3>

                @if (count($pendingPayments) === 0)
                    <div class="rounded-xl p-4 text-sm" style="background: var(--fi-section-bg, rgba(128,128,128,.08)); color: var(--fi-body-text-color, inherit); opacity: .75;">
                        Nenhum pagamento Pix/cartão pendente.
                    </div>
                @else
                    <div class="space-y-3">
                        @foreach ($pendingPayments as $payment)
                            <div
                                class="rounded-xl p-4"
                                style="border: 1px solid rgba(128,128,128,.25); background: var(--fi-section-bg, transparent);"
                            >
                                <div class="mb-2 flex items-center justify-between gap-3">
                                    <span class="font-mono text-xs" style="opacity:.65;">#{{ $payment['code'] }}</span>
                                    @if ($payment['expiring_soon'])
                                        <span class="rounded-full px-2 py-0.5 text-xs font-medium" style="background: rgba(220,38,38,.15); color: #dc2626;">
                                            {{ $payment['age_days'] }}d — expira em breve
                                        </span>
                                    @else
                                        <span class="rounded-full px-2 py-0.5 text-xs font-medium" style="background: rgba(255,193,7,.15); color: #b45309;">
                                            {{ $payment['age_days'] }}d
                                        </span>
                                    @endif
                                </div>
                                <div class="grid gap-2 text-sm sm:grid-cols-2" style="color: var(--fi-body-text-color, inherit); opacity: .9;">
                                    <div><span style="opacity:.65;">Método:</span> {{ $payment['method'] }}</div>
                                    <div><span style="opacity:.65;">Passageiro:</span> {{ $payment['passenger'] }}</div>
                                    <div class="sm:col-span-2"><span style="opacity:.65;">Motorista:</span> {{ $payment['driver'] }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
