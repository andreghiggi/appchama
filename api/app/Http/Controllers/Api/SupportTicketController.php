<?php

namespace App\Http\Controllers\Api;

use App\Domains\Support\Services\SupportService;
use App\Http\Controllers\Controller;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupportTicketController extends Controller
{
    public function __construct(private SupportService $support) {}

    /** Meus chamados (mais recentes primeiro). */
    public function index(Request $request): JsonResponse
    {
        $tickets = SupportTicket::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('last_message_at')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => $tickets->map(fn (SupportTicket $t) => $this->resumo($t))->values(),
            'categories' => SupportTicket::CATEGORIES,
            'unread' => $tickets->where('user_unread', true)->count(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category' => ['required', 'string', Rule::in(array_keys(SupportTicket::CATEGORIES))],
            'body' => ['required', 'string', 'min:5', 'max:2000'],
            'ride_id' => ['nullable', 'uuid'],
        ], [
            'body.required' => 'Conte o que aconteceu.',
            'body.min' => 'Escreva um pouco mais para a equipe entender.',
        ]);

        $ticket = $this->support->open($request->user(), $data['category'], $data['body'], $data['ride_id'] ?? null);

        return response()->json($this->detalhe($ticket), 201);
    }

    public function show(Request $request, SupportTicket $supportTicket): JsonResponse
    {
        abort_unless($supportTicket->user_id === $request->user()->id, 404);

        if ($supportTicket->user_unread) {
            $supportTicket->update(['user_unread' => false]);
        }

        return response()->json($this->detalhe($supportTicket));
    }

    public function reply(Request $request, SupportTicket $supportTicket): JsonResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'min:1', 'max:2000']]);

        $this->support->userReply($request->user(), $supportTicket, $data['body']);

        return response()->json($this->detalhe($supportTicket->fresh()));
    }

    public function close(Request $request, SupportTicket $supportTicket): JsonResponse
    {
        abort_unless($supportTicket->user_id === $request->user()->id, 404);

        return response()->json($this->resumo($this->support->close($supportTicket)));
    }

    /** @return array<string, mixed> */
    private function resumo(SupportTicket $t): array
    {
        return [
            'id' => $t->id,
            'category' => $t->category,
            'category_label' => $t->categoryLabel(),
            'subject' => $t->subject,
            'status' => $t->status,
            'status_label' => SupportTicket::STATUSES[$t->status] ?? $t->status,
            'ride_id' => $t->ride_id,
            'unread' => (bool) $t->user_unread,
            'last_message_at' => optional($t->last_message_at)->toIso8601String(),
            'created_at' => optional($t->created_at)->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function detalhe(SupportTicket $t): array
    {
        return $this->resumo($t) + [
            'messages' => $t->messages()->get()->map(fn (SupportMessage $m) => [
                'id' => $m->id,
                'author' => $m->author,
                'body' => $m->body,
                'created_at' => optional($m->created_at)->toIso8601String(),
            ])->values(),
        ];
    }
}
