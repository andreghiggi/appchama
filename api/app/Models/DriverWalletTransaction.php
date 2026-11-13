<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverWalletTransaction extends Model
{
    use \App\Models\Concerns\BelongsToTenant;
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'driver_id',
        'type',
        'amount',
        'balance_after',
        'prepaid_balance_after',
        'ride_id',
        'withdrawal_id',
        'description',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'prepaid_balance_after' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_id', 'user_id');
    }

    public function ride(): BelongsTo
    {
        return $this->belongsTo(Ride::class);
    }

    public function withdrawal(): BelongsTo
    {
        return $this->belongsTo(DriverWithdrawal::class, 'withdrawal_id');
    }

    public function tenantIdFromParent(): ?string
    {
        return \App\Models\Driver::withoutGlobalScopes()->where('user_id', $this->driver_id)->value('tenant_id');
    }
}
