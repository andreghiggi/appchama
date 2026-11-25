<x-filament-widgets::widget
    :attributes="
        (new \Filament\Support\View\ComponentAttributeBag)
            ->merge([
                'wire:poll.' . $this->getPollingInterval() => $this->getPollingInterval() ? true : null,
            ], escape: false)
    "
>
    @include('filament.widgets.partials.ac-ui')

    <x-filament::section heading="Saúde financeira" description="Vermelho = precisa de atenção agora.">
        <div class="ac-ui">
            <div class="ac-kpis">
                <div class="ac-kpi {{ count($negativeWallets) > 0 ? 'is-bad' : '' }}">
                    <span class="ac-kpi-n">{{ count($negativeWallets) }}</span>
                    <span class="ac-kpi-l">Carteiras negativas</span>
                </div>
                <div class="ac-kpi {{ count($pendingPayments) > 0 ? 'is-warn' : '' }}">
                    <span class="ac-kpi-n">{{ count($pendingPayments) }}</span>
                    <span class="ac-kpi-l">Pagamentos pendentes</span>
                </div>
            </div>

            <div class="ac-cols">
                <div>
                    <h3 class="ac-col-head">👛 Carteiras negativas <span class="ac-count">{{ count($negativeWallets) }}</span></h3>
                    @if (count($negativeWallets) === 0)
                        <div class="ac-empty"><span class="ac-empty-i">✅</span> Nenhum motorista com saldo negativo.</div>
                    @else
                        <div class="ac-list">
                            @foreach ($negativeWallets as $wallet)
                                <div class="ac-card is-red">
                                    <div class="ac-card-top">
                                        <span class="ac-title">{{ $wallet['name'] }}</span>
                                        <span class="ac-pill is-red">{{ $wallet['balance'] }}</span>
                                    </div>
                                    <div class="ac-grid">
                                        <div class="ac-f"><span class="ac-f-l">Telefone</span><span class="ac-f-v">{{ $wallet['phone'] }}</span></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div>
                    <h3 class="ac-col-head">⏳ Pagamentos pendentes <span class="ac-count">{{ count($pendingPayments) }}</span></h3>
                    @if (count($pendingPayments) === 0)
                        <div class="ac-empty"><span class="ac-empty-i">✅</span> Nenhum pagamento Pix/cartão pendente.</div>
                    @else
                        <div class="ac-list">
                            @foreach ($pendingPayments as $payment)
                                <div class="ac-card {{ $payment['expiring_soon'] ? 'is-red' : 'is-amber' }}">
                                    <div class="ac-card-top">
                                        <span class="ac-code">#{{ $payment['code'] }}</span>
                                        @if ($payment['expiring_soon'])
                                            <span class="ac-pill is-red">{{ $payment['age_days'] }}d — expira em breve</span>
                                        @else
                                            <span class="ac-pill is-amber">{{ $payment['age_days'] }}d</span>
                                        @endif
                                    </div>
                                    <div class="ac-grid">
                                        <div class="ac-f"><span class="ac-f-l">Método</span><span class="ac-f-v">{{ $payment['method'] }}</span></div>
                                        <div class="ac-f"><span class="ac-f-l">Passageiro</span><span class="ac-f-v">{{ $payment['passenger'] }}</span></div>
                                        <div class="ac-f"><span class="ac-f-l">Motorista</span><span class="ac-f-v">{{ $payment['driver'] }}</span></div>
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
