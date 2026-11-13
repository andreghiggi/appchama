<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('driver_wallets')) {
            Schema::create('driver_wallets', function (Blueprint $table): void {
                $table->foreignUuid('driver_id')->primary()->constrained('drivers', 'user_id')->cascadeOnDelete();
                $table->decimal('balance_available', 12, 2)->default(0);
                $table->decimal('prepaid_balance', 12, 2)->default(0);
                $table->decimal('total_earnings', 12, 2)->default(0);
                $table->timestamps();
            });
        } else {
            Schema::table('driver_wallets', function (Blueprint $table): void {
                if (! Schema::hasColumn('driver_wallets', 'prepaid_balance')) $table->decimal('prepaid_balance', 12, 2)->default(0)->after('balance_available');
                if (! Schema::hasColumn('driver_wallets', 'balance_available')) $table->decimal('balance_available', 12, 2)->default(0);
                if (! Schema::hasColumn('driver_wallets', 'total_earnings')) $table->decimal('total_earnings', 12, 2)->default(0);
            });
        }
        if (! Schema::hasTable('driver_wallet_transactions')) {
            Schema::create('driver_wallet_transactions', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('driver_id')->constrained('drivers', 'user_id')->cascadeOnDelete();
                $table->string('type', 30);
                $table->decimal('amount', 10, 2);
                $table->decimal('balance_after', 12, 2);
                $table->decimal('prepaid_balance_after', 12, 2)->nullable();
                $table->foreignUuid('ride_id')->nullable()->constrained('rides')->nullOnDelete();
                $table->uuid('withdrawal_id')->nullable();
                $table->string('description')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['driver_id', 'created_at']);
                $table->index(['ride_id', 'type']);
            });
        } else {
            Schema::table('driver_wallet_transactions', function (Blueprint $table): void {
                if (! Schema::hasColumn('driver_wallet_transactions', 'prepaid_balance_after')) $table->decimal('prepaid_balance_after', 12, 2)->nullable()->after('balance_after');
            });
        }
    }
    public function down(): void
    {
        // Intencionalmente sem remoção: esta migração repara schema de produção.
    }
};
