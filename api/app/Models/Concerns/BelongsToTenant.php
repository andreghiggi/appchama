<?php

namespace App\Models\Concerns;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;

trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model): void {
            if ($model->tenant_id) {
                return;
            }
            // 1) herda a marca do "pai" (usuário, motorista, corrida...) quando o modelo sabe como;
            if (method_exists($model, 'tenantIdFromParent')) {
                try {
                    $tenantId = $model->tenantIdFromParent();
                } catch (\Throwable) {
                    $tenantId = null;
                }
                if ($tenantId) {
                    $model->tenant_id = $tenantId;

                    return;
                }
            }
            // 2) senão usa a marca do contexto da requisição.
            if (app()->bound('currentTenantId')) {
                $model->tenant_id = app('currentTenantId');
            }
        });
    }
}
