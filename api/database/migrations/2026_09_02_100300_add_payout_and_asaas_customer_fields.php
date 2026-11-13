<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->string('payout_pix_key', 120)->nullable()->after('rating_avg');
            $table->string('payout_pix_key_type', 20)->nullable()->after('payout_pix_key');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('asaas_customer_id', 64)->nullable()->after('home_lng');
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropColumn(['payout_pix_key', 'payout_pix_key_type']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('asaas_customer_id');
        });
    }
};
