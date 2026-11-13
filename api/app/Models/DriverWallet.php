<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DriverWallet extends Model
{
    use \App\Models\Concerns\BelongsToTenant;
    protected $primaryKey = 'driver_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'driver_id',
        'balance_available',
        'prepaid_balance',
        'total_earnings',
    ];

    protected function casts(): array
    {
        return [
            'balance_available' => 'decimal:2',
            'prepaid_balance' => 'decimal:2',
            'total_earnings' => 'decimal:2',
        ];
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_id', 'user_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(DriverWalletTransaction::class, 'driver_id', 'driver_id');
    }

    public function tenantIdFromParent(): ?string
    {
        return \App\Models\Driver::withoutGlobalScopes()->where('user_id', $this->driver_id)->value('tenant_id');
    }
}
