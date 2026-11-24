<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            // corrida "encadeada": só começa depois que esta outra (a que o motorista está fazendo) terminar
            $table->uuid('follows_ride_id')->nullable()->after('driver_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->dropIndex(['follows_ride_id']);
            $table->dropColumn('follows_ride_id');
        });
    }
};
