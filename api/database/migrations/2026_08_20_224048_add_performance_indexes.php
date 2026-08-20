<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->index(['status', 'city_id']);
        });

        Schema::table('drivers', function (Blueprint $table) {
            $table->index(['city_id', 'online']);
        });

        Schema::table('ride_locations', function (Blueprint $table) {
            $table->index(['ride_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->dropIndex(['status', 'city_id']);
        });

        Schema::table('drivers', function (Blueprint $table) {
            $table->dropIndex(['city_id', 'online']);
        });

        Schema::table('ride_locations', function (Blueprint $table) {
            $table->dropIndex(['ride_id', 'recorded_at']);
        });
    }
};
