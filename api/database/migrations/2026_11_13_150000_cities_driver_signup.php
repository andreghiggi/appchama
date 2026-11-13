<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Cadastro de motoristas pelo app por cidade (padrao: aberto, como era antes). */
    public function up(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            $table->boolean('driver_signup_enabled')->default(true)->after('rides_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            $table->dropColumn('driver_signup_enabled');
        });
    }
};
