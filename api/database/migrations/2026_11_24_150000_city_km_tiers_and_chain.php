<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            // faixas de distância: [{"from_km": 5, "price": 3.50}, ...] — a partir de from_km cada km passa a custar price
            $table->json('km_tiers')->nullable()->after('price_per_min');
            // corrida com passageiro no carro (encadeada), ajustável por cidade
            $table->boolean('chain_rides_enabled')->default(true)->after('min_fare');
            $table->unsignedTinyInteger('chain_min_progress_pct')->default(20)->after('chain_rides_enabled');
            $table->decimal('chain_max_pickup_km', 4, 1)->default(5.0)->after('chain_min_progress_pct');
        });
    }

    public function down(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            $table->dropColumn(['km_tiers', 'chain_rides_enabled', 'chain_min_progress_pct', 'chain_max_pickup_km']);
        });
    }
};
