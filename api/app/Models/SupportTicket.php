<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    use BelongsToTenant, HasUuids;

    /** Assuntos que o usuário escolhe no app. */
    public const CATEGORIES = [
        'ride' => 'Problema com uma corrida',
        'payment' => 'Pagamento, crédito ou saque',
        'report' => 'Denúncia / segurança',
        'account' => 'Minha conta e cadastro',
        'app' => 'Erro ou dúvida no app',
        'other' => 'Outro assunto',
    ];

    public const STATUSES = [
        'open' => 'Aberto',
        'in_progress' => 'Em atendimento',
        'closed' => 'Encerrado',
    ];

    protected $fillable = [
        'tenant_id',
        'user_id',
        'ride_id',
        'category',
        'subject',
        'status',
        'last_message_at',
        'user_unread',
        'staff_unread',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'user_unread' => 'boolean',
            'staff_unread' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ride(): BelongsTo
    {
        return $this->belongsTo(Ride::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class)->orderBy('created_at');
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? self::CATEGORIES['other'];
    }
}
