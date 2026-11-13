<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RideLocation extends Model
{
    use \App\Models\Concerns\BelongsToTenant;
    public $timestamps = false;

    protected $fillable = [
        'ride_id',
        'lat',
        'lng',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
            'recorded_at' => 'datetime',
        ];
    }

    public function ride(): BelongsTo
    {
        return $this->belongsTo(Ride::class);
    }

    public function tenantIdFromParent(): ?string
    {
        return \App\Models\Ride::withoutGlobalScopes()->whereKey($this->ride_id)->value('tenant_id');
    }
}
