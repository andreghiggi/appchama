<x-filament-widgets::widget>
    <x-filament::section heading="Mapa ao vivo">
        <div
            id="live-map"
            style="height: 280px; border-radius: 12px; background: #F3F6F8; position: relative; overflow: hidden;"
        >
            @forelse ($drivers as $driver)
                <div
                    title="{{ $driver->user?->name }}"
                    style="position: absolute; left: {{ rand(10, 90) }}%; top: {{ rand(10, 80) }}%; width: 28px; height: 28px; background: #FF9F1C; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; box-shadow: 0 4px 10px rgba(255,159,28,.4);"
                >🚗</div>
            @empty
                <div style="display:flex;align-items:center;justify-content:center;height:100%;color:#5B6472;">
                    Nenhum motorista online no momento
                </div>
            @endforelse
        </div>
        <p style="margin-top:8px;font-size:12px;color:#9AA2AD;">
            {{ $drivers->count() }} motorista(s) online — atualize a página para ver posições recentes.
        </p>
    </x-filament::section>
</x-filament-widgets::widget>
