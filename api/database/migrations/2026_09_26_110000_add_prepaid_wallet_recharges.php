<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('driver_wallets', function (Blueprint $table): void {
            $table->decimal('prepaid_balance', 12, 2)->default(0)->after('balance_available');
        });
        Schema::table('driver_wallet_transactions', function (Blueprint $table): void {
            $table->decimal('prepaid_balance_after', 12, 2)->nullable()->after('balance_after');
            $table->index(['ride_id', 'type']);
        });
        Schema::create('driver_wallet_recharges', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('driver_id')->constrained('drivers', 'user_id')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('status', 20)->default('pending');
            $table->string('asaas_payment_id', 64)->nullable()->unique();
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->index(['driver_id', 'status']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('driver_wallet_recharges');
        Schema::table('driver_wallet_transactions', function (Blueprint $table): void { $table->dropIndex(['ride_id', 'type']); $table->dropColumn('prepaid_balance_after'); });
        Schema::table('driver_wallets', function (Blueprint $table): void { $table->dropColumn('prepaid_balance'); });
    }
};
