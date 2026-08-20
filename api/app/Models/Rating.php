<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rating extends Model
{
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
}
