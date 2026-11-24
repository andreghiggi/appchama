<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Adicional cobrado "por fora" pelo motorista durante a corrida (parada, espera, distância extra).
        // Não entra no valor da corrida, no pagamento do app nem na comissão: o passageiro paga direto ao motorista.
        Schema::create('ride_extras', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('ride_id')->constrained('rides')->cascadeOnDelete();
            $table->foreignUuid('driver_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 8, 2);
            // stop | wait | distance | other
            $table->string('reason', 12);
            $table->string('note', 120)->nullable();
            // pending (aguardando o passageiro) | accepted | disputed (passageiro não concordou) | canceled (motorista desfez)
            $table->string('status', 12)->default('pending');
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
            $table->index(['ride_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ride_extras');
    }
};
