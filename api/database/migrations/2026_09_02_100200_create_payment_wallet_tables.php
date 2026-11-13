<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ride_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('ride_id')->constrained('rides')->cascadeOnDelete();
            $table->string('method', 20);
            $table->decimal('amount', 10, 2);
            $table->string('status', 20)->default('pending');
            $table->string('asaas_payment_id', 64)->nullable();
            $table->string('asaas_checkout_id', 64)->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['ride_id', 'status']);
            $table->index('asaas_payment_id');
        });

        Schema::create('driver_wallets', function (Blueprint $table) {
            $table->foreignUuid('driver_id')->primary()->constrained('drivers', 'user_id')->cascadeOnDelete();
            $table->decimal('balance_available', 12, 2)->default(0);
            $table->decimal('total_earnings', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('driver_wallet_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('driver_id')->constrained('drivers', 'user_id')->cascadeOnDelete();
            $table->string('type', 30);
            $table->decimal('amount', 10, 2);
            $table->decimal('balance_after', 12, 2);
            $table->foreignUuid('ride_id')->nullable()->constrained('rides')->nullOnDelete();
            $table->uuid('withdrawal_id')->nullable();
            $table->string('description')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['driver_id', 'created_at']);
        });

        Schema::create('driver_withdrawals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('driver_id')->constrained('drivers', 'user_id')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('status', 20)->default('pending');
            $table->string('pix_key', 120);
            $table->string('pix_key_type', 20);
            $table->string('asaas_transfer_id', 64)->nullable();
            $table->string('fail_reason')->nullable();
            $table->timestamps();

            $table->index(['driver_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_withdrawals');
        Schema::dropIfExists('driver_wallet_transactions');
        Schema::dropIfExists('driver_wallets');
        Schema::dropIfExists('ride_payments');
    }
};
