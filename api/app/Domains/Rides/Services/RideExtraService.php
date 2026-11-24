<?php

namespace App\Domains\Rides\Services;

use App\Domains\Notifications\Services\PushService;
use App\Domains\Support\Services\SupportService;
use App\Models\Ride;
use App\Models\RideExtra;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Adicional "por fora": o motorista registra durante a corrida um valor a mais (parada, espera, distância extra).
 * O passageiro vê, concorda ou discorda. O valor NÃO soma na corrida, no pagamento do app nem na comissão.
 */
class RideExtraService
{
    public function __construct(private PushService $push, private SupportService $support) {}

    public function create(Ride $ride, User $driver, float $amount, string $reason, ?string $note): RideExtra
    {
        abort_unless($driver->isDriver() && $ride->driver_id === $driver->id, 403, 'Esta corrida não é sua.');
        abort_unless($ride->status === 'in_progress', 422, 'O adicional só pode ser pedido com a viagem em andamento.');
        abort_unless(array_key_exists($reason, RideExtra::REASONS), 422, 'Escolha o motivo do adicional.');
        abort_unless($amount >= 1 && $amount <= RideExtra::MAX_AMOUNT, 422, sprintf('O adicional deve ser entre R$ 1,00 e R$ %s.', number_format(RideExtra::MAX_AMOUNT, 2, ',', '.')));

        $extra = DB::transaction(function () use ($ride, $driver, $amount, $reason, $note): RideExtra {
            $ativos = RideExtra::query()->where('ride_id', $ride->id)->where('status', '!=', 'canceled')->lockForUpdate()->count();
            abort_if($ativos >= RideExtra::MAX_PER_RIDE, 422, 'Limite de '.RideExtra::MAX_PER_RIDE.' adicionais por corrida.');
            abort_if(RideExtra::query()->where('ride_id', $ride->id)->where('status', 'pending')->exists(), 422, 'Aguarde o passageiro responder ao adicional anterior.');

            return RideExtra::query()->create([
                'tenant_id' => $ride->tenant_id,
                'ride_id' => $ride->id,
                'driver_id' => $driver->id,
                'amount' => round($amount, 2),
                'reason' => $reason,
                'note' => $note ? mb_substr(trim($note), 0, 120) : null,
                'status' => 'pending',
            ]);
        });

        $this->push->toUser((string) $ride->passenger_id, 'Adicional pedido pelo motorista',
            sprintf('%s: R$ %s, pago direto ao motorista. Abra o app para responder.', $extra->reasonLabel(), number_format((float) $extra->amount, 2, ',', '.')),
            ['type' => 'ride_extra', 'ride_id' => $ride->id, 'extra_id' => $extra->id]);

        return $extra;
    }

    /** O passageiro concorda: fica registrado; o pagamento é direto com o motorista. */
    public function accept(Ride $ride, User $passenger, RideExtra $extra): RideExtra
    {
        $this->exigirPendenteDoPassageiro($ride, $passenger, $extra);
        $extra->update(['status' => 'accepted', 'responded_at' => now()]);
        $this->push->toUser((string) $extra->driver_id, 'Adicional aceito',
            'O passageiro concordou com o adicional de R$ '.number_format((float) $extra->amount, 2, ',', '.').'. Receba direto dele.',
            ['type' => 'ride_extra', 'ride_id' => $ride->id, 'extra_id' => $extra->id]);

        return $extra->fresh();
    }

    /** O passageiro não concorda: o motorista é avisado e abre-se um chamado para a equipe analisar. */
    public function dispute(Ride $ride, User $passenger, RideExtra $extra): RideExtra
    {
        $this->exigirPendenteDoPassageiro($ride, $passenger, $extra);
        $extra->update(['status' => 'disputed', 'responded_at' => now()]);

        $this->support->open(
            $passenger,
            'ride',
            sprintf('Não concordei com um adicional cobrado pelo motorista na corrida: %s, R$ %s%s.',
                $extra->reasonLabel(), number_format((float) $extra->amount, 2, ',', '.'), $extra->note ? ' ("'.$extra->note.'")' : ''),
            $ride->id,
            ignoreLimit: true,
        );
        $this->push->toUser((string) $extra->driver_id, 'Adicional não aceito',
            'O passageiro não concordou com o adicional. A equipe vai analisar.',
            ['type' => 'ride_extra', 'ride_id' => $ride->id, 'extra_id' => $extra->id]);

        return $extra->fresh();
    }

    public function cancel(Ride $ride, User $driver, RideExtra $extra): RideExtra
    {
        abort_unless($ride->driver_id === $driver->id && $extra->ride_id === $ride->id, 403, 'Esta corrida não é sua.');
        abort_unless($extra->status === 'pending', 422, 'Só dá para desfazer um adicional que o passageiro ainda não respondeu.');
        $extra->update(['status' => 'canceled', 'responded_at' => now()]);

        return $extra->fresh();
    }

    /** @return \Illuminate\Support\Collection<int, RideExtra> */
    public function listFor(Ride $ride, User $user)
    {
        abort_unless($ride->passenger_id === $user->id || $ride->driver_id === $user->id, 404);

        return RideExtra::query()->where('ride_id', $ride->id)->orderBy('created_at')->get();
    }

    private function exigirPendenteDoPassageiro(Ride $ride, User $passenger, RideExtra $extra): void
    {
        abort_unless($ride->passenger_id === $passenger->id && $extra->ride_id === $ride->id, 404);
        abort_unless($extra->status === 'pending', 422, 'Este adicional já foi respondido.');
    }
}
