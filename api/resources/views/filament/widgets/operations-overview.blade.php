<x-filament-widgets::widget
    :attributes="
        (new \Filament\Support\View\ComponentAttributeBag)
            ->merge([
                'wire:poll.' . $this->getPollingInterval() => $this->getPollingInterval() ? true : null,
            ], escape: false)
    "
>
    <x-filament::section heading="Operação ao vivo">
        <div class="grid gap-6 xl:grid-cols-2">
            <div>
                <h3 class="mb-3 text-sm font-semibold" style="color: var(--fi-body-text-color, inherit);">
                    Motoristas em corrida ({{ count($driversInRide) }})
                </h3>

                @if (count($driversInRide) === 0)
                    <div class="rounded-xl p-4 text-sm" style="background: var(--fi-section-bg, rgba(128,128,128,.08)); color: var(--fi-body-text-color, inherit); opacity: .75;">
                        Nenhum motorista com corrida em andamento.
                    </div>
                @else
                    <div class="space-y-3">
                        @foreach ($driversInRide as $driver)
                            <div
                                class="rounded-xl p-4"
                                style="border: 1px solid rgba(128,128,128,.25); background: var(--fi-section-bg, transparent);"
                            >
                                <div class="mb-2 flex items-center justify-between gap-3">
                                    <span class="text-base font-semibold" style="color: var(--fi-body-text-color, inherit);">
                                        🚗 {{ $driver['name'] }}
                                    </span>
                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium" style="background: rgba(255,159,28,.15); color: #ff9f1c;">
                                        {{ $driver['status'] }}
                                    </span>
                                </div>
                                <div class="grid gap-2 text-sm sm:grid-cols-2" style="color: var(--fi-body-text-color, inherit); opacity: .9;">
                                    <div><span style="opacity:.65;">Telefone:</span> {{ $driver['phone'] }}</div>
                                    <div><span style="opacity:.65;">Cidade:</span> {{ $driver['city'] }}</div>
                                    <div class="sm:col-span-2"><span style="opacity:.65;">Passageiro:</span> {{ $driver['passenger'] }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div>
                <h3 class="mb-3 text-sm font-semibold" style="color: var(--fi-body-text-color, inherit);">
                    Corridas ativas ({{ count($activeRides) }})
                </h3>

                @if (count($activeRides) === 0)
                    <div class="rounded-xl p-4 text-sm" style="background: var(--fi-section-bg, rgba(128,128,128,.08)); color: var(--fi-body-text-color, inherit); opacity: .75;">
                        Nenhuma corrida em andamento.
                    </div>
                @else
                    <div class="space-y-3">
                        @foreach ($activeRides as $ride)
                            <div
                                class="rounded-xl p-4"
                                style="border: 1px solid rgba(128,128,128,.25); background: var(--fi-section-bg, transparent);"
                            >
                                <div class="mb-2 flex items-center justify-between gap-3">
                                    <span class="font-mono text-xs" style="opacity:.65;">#{{ $ride['code'] }}</span>
                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium" style="background: rgba(15,158,141,.15); color: #0f9e8d;">
                                        {{ $ride['status'] }}
                                    </span>
                                </div>
                                <div class="grid gap-2 text-sm sm:grid-cols-2" style="color: var(--fi-body-text-color, inherit); opacity: .9;">
                                    <div><span style="opacity:.65;">Passageiro:</span> {{ $ride['passenger'] }}</div>
                                    <div><span style="opacity:.65;">Tel. passageiro:</span> {{ $ride['passenger_phone'] }}</div>
                                    <div><span style="opacity:.65;">Motorista:</span> {{ $ride['driver'] }}</div>
                                    <div><span style="opacity:.65;">Tel. motorista:</span> {{ $ride['driver_phone'] }}</div>
                                    <div><span style="opacity:.65;">Cidade:</span> {{ $ride['city'] }}</div>
                                    <div><span style="opacity:.65;">Estimativa:</span> {{ $ride['fare'] }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="mt-6">
            <h3 class="mb-3 text-sm font-semibold" style="color: var(--fi-body-text-color, inherit);">
                Aguardando pagamento ({{ count($awaitingPayment) }})
            </h3>

            @if (count($awaitingPayment) === 0)
                <div class="rounded-xl p-4 text-sm" style="background: var(--fi-section-bg, rgba(128,128,128,.08)); color: var(--fi-body-text-color, inherit); opacity: .75;">
                    Nenhuma corrida aguardando pagamento.
                </div>
            @else
                <div class="grid gap-3 md:grid-cols-2">
                    @foreach ($awaitingPayment as $ride)
                        <div
                            class="rounded-xl p-4"
                            style="border: 1px solid rgba(128,128,128,.25); background: var(--fi-section-bg, transparent);"
                        >
                            <div class="mb-2 flex items-center justify-between gap-3">
                                <span class="font-mono text-xs" style="opacity:.65;">#{{ $ride['code'] }}</span>
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium" style="background: rgba(255,193,7,.15); color: #b45309;">
                                    Aguardando pagamento
                                </span>
                            </div>
                            <div class="grid gap-2 text-sm sm:grid-cols-2" style="color: var(--fi-body-text-color, inherit); opacity: .9;">
                                <div><span style="opacity:.65;">Passageiro:</span> {{ $ride['passenger'] }}</div>
                                <div><span style="opacity:.65;">Motorista:</span> {{ $ride['driver'] }}</div>
                                <div><span style="opacity:.65;">Valor:</span> {{ $ride['fare'] }}</div>
                                <div><span style="opacity:.65;">Líquido motorista:</span> {{ $ride['net'] }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
