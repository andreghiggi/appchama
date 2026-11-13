<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPayment extends Model
{
    use \App\Models\Concerns\BelongsToTenant;
    use HasUuids;

    protected $fillable = [
        'subscription_id',
        'amount',
        'paid_at',
        'status',
        'gateway_reference',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function tenantIdFromParent(): ?string
    {
        return \App\Models\Subscription::withoutGlobalScopes()->whereKey($this->subscription_id)->value('tenant_id');
    }
}
