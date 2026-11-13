<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->text('asaas_api_key')->nullable()->after('status');
            $table->string('asaas_environment', 20)->default('sandbox')->after('asaas_api_key');
            $table->string('asaas_webhook_token', 64)->nullable()->after('asaas_environment');
            $table->decimal('platform_commission_pct', 5, 2)->default(0)->after('asaas_webhook_token');
            $table->json('payment_methods_enabled')->nullable()->after('platform_commission_pct');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'asaas_api_key',
                'asaas_environment',
                'asaas_webhook_token',
                'platform_commission_pct',
                'payment_methods_enabled',
            ]);
        });
    }
};
