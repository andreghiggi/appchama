<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rating extends Model
{
    use \App\Models\Concerns\BelongsToTenant;
    use HasUuids;

    protected $fillable = [
        'ride_id',
        'rated_by',
        'score',
        'comment',
    ];

    public function ride(): BelongsTo
    {
        return $this->belongsTo(Ride::class);
    }

    public function tenantIdFromParent(): ?string
    {
        return \App\Models\Ride::withoutGlobalScopes()->whereKey($this->ride_id)->value('tenant_id');
    }
}
