<?php

namespace App\Listeners;

use App\Domains\Notifications\Services\PushService;
use App\Events\RideOfferReceived;
use App\Events\RideStatusChanged;

class EnviarPushDaCorrida
{
    public function __construct(private PushService $push) {}

    public function handleOffer(RideOfferReceived $event): void
    {
        $this->push->toUser($event->driverId, 'Nova corrida', 'Há um passageiro pedindo corrida perto de você. Toque para ver.', [
            'tipo' => 'oferta', 'ride_id' => $event->ride->id,
        ]);
    }

    public function handleStatus(RideStatusChanged $event): void
    {
        $ride = $event->ride;
        $dados = ['tipo' => 'corrida', 'ride_id' => $ride->id, 'status' => $ride->status];

        match ($ride->status) {
            'accepted' => $this->push->toUser($ride->passenger_id, 'Motorista a caminho', 'Um motorista aceitou sua corrida.', $dados),
            'driver_arrived' => $this->push->toUser($ride->passenger_id, 'Seu motorista chegou', 'Ele está no local de embarque.', $dados),
            'awaiting_payment' => $this->push->toUser($ride->passenger_id, 'Corrida finalizada', 'Toque para fazer o pagamento.', $dados),
            'canceled_by_driver' => $this->push->toUser($ride->passenger_id, 'O motorista cancelou', 'Você pode pedir outra corrida.', $dados),
            'no_drivers_available' => $this->push->toUser($ride->passenger_id, 'Sem motoristas agora', 'Nenhum motorista disponível. Tente de novo em instantes.', $dados),
            'canceled_by_passenger' => $ride->driver_id
                ? $this->push->toUser($ride->driver_id, 'Corrida cancelada', 'O passageiro cancelou a corrida.', $dados)
                : null,
            default => null,
        };
    }
}
