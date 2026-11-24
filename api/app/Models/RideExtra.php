<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RideExtra extends Model
{
    use BelongsToTenant, HasUuids;

    public const REASONS = [
        'stop' => 'Parada no caminho',
        'wait' => 'Tempo de espera',
        'distance' => 'Distância a mais',
        'other' => 'Outro motivo',
    ];

    /** Limites para evitar abuso: valor de cada adicional e quantidade por corrida. */
    public const MAX_AMOUNT = 200.00;

    public const MAX_PER_RIDE = 5;

    protected $fillable = ['tenant_id', 'ride_id', 'driver_id', 'amount', 'reason', 'note', 'status', 'responded_at'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'responded_at' => 'datetime'];
    }

    public function ride(): BelongsTo
    {
        return $this->belongsTo(Ride::class);
    }

    public function reasonLabel(): string
    {
        return self::REASONS[$this->reason] ?? self::REASONS['other'];
    }
}
