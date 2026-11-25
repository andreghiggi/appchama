<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Carteira unica do motorista: o saldo de comissao (prepaid_balance) passa para balance_available.
 * activated_at marca a primeira recarga (depois dela o motorista pode operar ate -R$ 20).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('driver_wallets', 'activated_at')) {
            Schema::table('driver_wallets', function (Blueprint $table): void {
                $table->timestamp('activated_at')->nullable();
            });
        }

        // quem ja recarregou (ou recebeu credito manual) ou tem saldo de comissao ja esta ativado
        DB::table('driver_wallets')->whereNull('activated_at')->where(function ($q): void {
            $q->where('prepaid_balance', '!=', 0)
                ->orWhereIn('driver_id', DB::table('driver_wallet_transactions')->whereIn('type', ['wallet_recharge', 'manual_credit'])->select('driver_id'));
        })->update(['activated_at' => now()]);

        DB::table('driver_wallets')->update([
            'balance_available' => DB::raw('balance_available + prepaid_balance'),
            'prepaid_balance' => 0,
        ]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('driver_wallets', 'activated_at')) {
            Schema::table('driver_wallets', function (Blueprint $table): void {
                $table->dropColumn('activated_at');
            });
        }
    }
};
