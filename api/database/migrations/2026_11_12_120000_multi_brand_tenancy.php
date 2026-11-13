<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Tabelas filhas que passam a carregar tenant_id (isolamento por marca). */
    private array $filhas = [
        'drivers', 'vehicles', 'driver_wallets', 'driver_wallet_transactions',
        'ratings', 'ride_locations', 'subscriptions', 'subscription_payments',
    ];

    public function up(): void
    {
        Schema::create('tenant_domains', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id');
            $table->string('host')->unique();
            $table->string('type', 10)->default('api'); // api | admin | site
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_platform_admin')->default(false)->after('status');
        });
        // O administrador atual do AppChama passa a ser administrador da plataforma (vê todas as marcas).
        DB::table('users')->where('role', 'admin')->update(['is_platform_admin' => true]);

        foreach ($this->filhas as $tabela) {
            if (! Schema::hasColumn($tabela, 'tenant_id')) {
                Schema::table($tabela, function (Blueprint $table) {
                    $table->uuid('tenant_id')->nullable()->index();
                });
            }
        }

        // Preenche a marca de cada registro a partir do "pai".
        DB::statement('UPDATE drivers d JOIN users u ON u.id = d.user_id SET d.tenant_id = u.tenant_id');
        DB::statement('UPDATE vehicles v JOIN drivers d ON d.user_id = v.driver_id SET v.tenant_id = d.tenant_id');
        DB::statement('UPDATE driver_wallets w JOIN drivers d ON d.user_id = w.driver_id SET w.tenant_id = d.tenant_id');
        DB::statement('UPDATE driver_wallet_transactions x JOIN drivers d ON d.user_id = x.driver_id SET x.tenant_id = d.tenant_id');
        DB::statement('UPDATE subscriptions s JOIN drivers d ON d.user_id = s.driver_id SET s.tenant_id = d.tenant_id');
        DB::statement('UPDATE subscription_payments p JOIN subscriptions s ON s.id = p.subscription_id SET p.tenant_id = s.tenant_id');
        DB::statement('UPDATE ratings r JOIN rides ri ON ri.id = r.ride_id SET r.tenant_id = ri.tenant_id');
        DB::statement('UPDATE ride_locations l JOIN rides ri ON ri.id = l.ride_id SET l.tenant_id = ri.tenant_id');
    }

    public function down(): void
    {
        foreach ($this->filhas as $tabela) {
            if (Schema::hasColumn($tabela, 'tenant_id')) {
                Schema::table($tabela, function (Blueprint $table) {
                    $table->dropIndex(['tenant_id']);
                    $table->dropColumn('tenant_id');
                });
            }
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_platform_admin');
        });
        Schema::dropIfExists('tenant_domains');
    }
};
