<?php

namespace App\Domains\Support\Services;

use App\Domains\Notifications\Services\PushService;
use App\Models\Ride;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Chamados de suporte (passageiro e motorista falam com a equipe da marca).
 * O usuário abre o chamado e conversa; a equipe responde pelo painel e o app recebe um aviso.
 */
class SupportService
{
    /** Quantos chamados abertos cada pessoa pode ter ao mesmo tempo (evita enxurrada de pedidos iguais). */
    public const MAX_OPEN_PER_USER = 5;

    public function __construct(private PushService $push) {}

    public function open(User $user, string $category, string $body, ?string $rideId = null, bool $ignoreLimit = false): SupportTicket
    {
        abort_unless(array_key_exists($category, SupportTicket::CATEGORIES), 422, 'Escolha o assunto do chamado.');

        $abertos = SupportTicket::query()->where('user_id', $user->id)->where('status', '!=', 'closed')->count();
        abort_if(! $ignoreLimit && $abertos >= self::MAX_OPEN_PER_USER, 422, 'Você já tem '.self::MAX_OPEN_PER_USER.' chamados abertos. Aguarde a resposta ou encerre algum antes de abrir outro.');

        if ($rideId) {
            // só liga a uma corrida em que a pessoa participou
            $ride = Ride::query()->find($rideId);
            abort_unless($ride && ($ride->passenger_id === $user->id || $ride->driver_id === $user->id), 422, 'Corrida não encontrada.');
        }

        return DB::transaction(function () use ($user, $category, $body, $rideId): SupportTicket {
            $ticket = SupportTicket::query()->create([
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'ride_id' => $rideId,
                'category' => $category,
                'subject' => Str::limit(SupportTicket::CATEGORIES[$category].' — '.trim(preg_replace('/\s+/', ' ', $body)), 150, '…'),
                'status' => 'open',
                'last_message_at' => now(),
                'user_unread' => false,
                'staff_unread' => true,
            ]);
            $this->addMessage($ticket, $user, 'user', $body, touch: false);

            return $ticket->fresh();
        });
    }

    /** Mensagem do usuário num chamado dele (reabre se estava encerrado). */
    public function userReply(User $user, SupportTicket $ticket, string $body): SupportMessage
    {
        abort_unless($ticket->user_id === $user->id, 404);

        $msg = $this->addMessage($ticket, $user, 'user', $body);
        $ticket->update([
            'status' => $ticket->status === 'closed' ? 'open' : $ticket->status,
            'staff_unread' => true,
        ]);

        return $msg;
    }

    /** Resposta da equipe (painel): marca como "em atendimento" e avisa o usuário no celular. */
    public function staffReply(User $staff, SupportTicket $ticket, string $body): SupportMessage
    {
        $msg = $this->addMessage($ticket, $staff, 'staff', $body);
        $ticket->update([
            'status' => $ticket->status === 'open' ? 'in_progress' : $ticket->status,
            'user_unread' => true,
            'staff_unread' => false,
        ]);

        $this->push->toUser(
            (string) $ticket->user_id,
            'Resposta do suporte',
            Str::limit($body, 120),
            ['type' => 'support_reply', 'ticket_id' => $ticket->id],
        );

        return $msg;
    }

    public function close(SupportTicket $ticket): SupportTicket
    {
        $ticket->update(['status' => 'closed', 'staff_unread' => false]);

        return $ticket->fresh();
    }

    private function addMessage(SupportTicket $ticket, User $autor, string $author, string $body, bool $touch = true): SupportMessage
    {
        $msg = SupportMessage::query()->create([
            'tenant_id' => $ticket->tenant_id,
            'support_ticket_id' => $ticket->id,
            'user_id' => $autor->id,
            'author' => $author,
            'body' => trim($body),
        ]);
        if ($touch) {
            $ticket->update(['last_message_at' => now()]);
        }

        return $msg;
    }
}
