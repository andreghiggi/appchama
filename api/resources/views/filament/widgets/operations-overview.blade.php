<x-filament-widgets::widget
    :attributes="
        (new \Filament\Support\View\ComponentAttributeBag)
            ->merge([
                'wire:poll.' . $this->getPollingInterval() => $this->getPollingInterval() ? true : null,
            ], escape: false)
    "
>
    @include('filament.widgets.partials.ac-ui')

    <x-filament::section heading="Operação ao vivo" description="Atualiza sozinho. Verde = em andamento, âmbar = precisa de atenção.">
        <div class="ac-ui">
            <div class="ac-kpis">
                <div class="ac-kpi {{ count($driversInRide) > 0 ? 'is-on' : '' }}">
                    <span class="ac-kpi-n">{{ count($driversInRide) }}</span>
                    <span class="ac-kpi-l">Motoristas em corrida</span>
                </div>
                <div class="ac-kpi {{ count($activeRides) > 0 ? 'is-on' : '' }}">
                    <span class="ac-kpi-n">{{ count($activeRides) }}</span>
                    <span class="ac-kpi-l">Corridas ativas</span>
                </div>
                <div class="ac-kpi {{ count($awaitingPayment) > 0 ? 'is-warn' : '' }}">
                    <span class="ac-kpi-n">{{ count($awaitingPayment) }}</span>
                    <span class="ac-kpi-l">Aguardando pagamento</span>
                </div>
            </div>

            <div class="ac-cols">
                <div>
                    <h3 class="ac-col-head">🚗 Motoristas em corrida <span class="ac-count">{{ count($driversInRide) }}</span></h3>
                    @if (count($driversInRide) === 0)
                        <div class="ac-empty"><span class="ac-empty-i">😴</span> Nenhum motorista com corrida em andamento.</div>
                    @else
                        <div class="ac-list">
                            @foreach ($driversInRide as $driver)
                                <div class="ac-card is-teal">
                                    <div class="ac-card-top">
                                        <span class="ac-title">{{ $driver['name'] }}</span>
                                        <span class="ac-pill is-teal">{{ $driver['status'] }}</span>
                                    </div>
                                    <div class="ac-grid">
                                        <div class="ac-f"><span class="ac-f-l">Telefone</span><span class="ac-f-v">{{ $driver['phone'] }}</span></div>
                                        <div class="ac-f"><span class="ac-f-l">Cidade</span><span class="ac-f-v">{{ $driver['city'] }}</span></div>
                                        <div class="ac-f"><span class="ac-f-l">Passageiro</span><span class="ac-f-v">{{ $driver['passenger'] }}</span></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div>
                    <h3 class="ac-col-head">📍 Corridas ativas <span class="ac-count">{{ count($activeRides) }}</span></h3>
                    @if (count($activeRides) === 0)
                        <div class="ac-empty"><span class="ac-empty-i">🛣️</span> Nenhuma corrida em andamento.</div>
                    @else
                        <div class="ac-list">
                            @foreach ($activeRides as $ride)
                                <div class="ac-card is-teal">
                                    <div class="ac-card-top">
                                        <span class="ac-code">#{{ $ride['code'] }}</span>
                                        <span class="ac-pill is-teal">{{ $ride['status'] }}</span>
                                    </div>
                                    <div class="ac-grid">
                                        <div class="ac-f"><span class="ac-f-l">Passageiro</span><span class="ac-f-v">{{ $ride['passenger'] }}</span></div>
                                        <div class="ac-f"><span class="ac-f-l">Tel. passageiro</span><span class="ac-f-v">{{ $ride['passenger_phone'] }}</span></div>
                                        <div class="ac-f"><span class="ac-f-l">Motorista</span><span class="ac-f-v">{{ $ride['driver'] }}</span></div>
                                        <div class="ac-f"><span class="ac-f-l">Tel. motorista</span><span class="ac-f-v">{{ $ride['driver_phone'] }}</span></div>
                                        <div class="ac-f"><span class="ac-f-l">Cidade</span><span class="ac-f-v">{{ $ride['city'] }}</span></div>
                                        <div class="ac-f"><span class="ac-f-l">Estimativa</span><span class="ac-f-v is-money">{{ $ride['fare'] }}</span></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div>
                    <h3 class="ac-col-head">💳 Aguardando pagamento <span class="ac-count">{{ count($awaitingPayment) }}</span></h3>
                    @if (count($awaitingPayment) === 0)
                        <div class="ac-empty"><span class="ac-empty-i">✅</span> Nenhuma corrida aguardando pagamento.</div>
                    @else
                        <div class="ac-list">
                            @foreach ($awaitingPayment as $ride)
                                <div class="ac-card is-amber">
                                    <div class="ac-card-top">
                                        <span class="ac-code">#{{ $ride['code'] }}</span>
                                        <span class="ac-pill is-amber">Aguardando pagamento</span>
                                    </div>
                                    <div class="ac-grid">
                                        <div class="ac-f"><span class="ac-f-l">Passageiro</span><span class="ac-f-v">{{ $ride['passenger'] }}</span></div>
                                        <div class="ac-f"><span class="ac-f-l">Motorista</span><span class="ac-f-v">{{ $ride['driver'] }}</span></div>
                                        <div class="ac-f"><span class="ac-f-l">Valor</span><span class="ac-f-v is-money">{{ $ride['fare'] }}</span></div>
                                        <div class="ac-f"><span class="ac-f-l">Líquido motorista</span><span class="ac-f-v is-money">{{ $ride['net'] }}</span></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
