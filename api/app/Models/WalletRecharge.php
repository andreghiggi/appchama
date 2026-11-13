<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class WalletRecharge extends Model {
    use \App\Models\Concerns\BelongsToTenant;
    use HasUuids;
    protected $fillable = ['tenant_id','driver_id','amount','status','asaas_payment_id','payload'];
    protected function casts(): array { return ['amount'=>'decimal:2','payload'=>'array']; }
    public function driver(): BelongsTo { return $this->belongsTo(Driver::class, 'driver_id', 'user_id'); }
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }

    public function tenantIdFromParent(): ?string
    {
        return \App\Models\Driver::withoutGlobalScopes()->where('user_id', $this->driver_id)->value('tenant_id');
    }
}
