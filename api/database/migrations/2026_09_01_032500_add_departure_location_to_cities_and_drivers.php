<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            $table->string('departure_address')->nullable()->after('active');
            $table->decimal('departure_lat', 10, 7)->nullable()->after('departure_address');
            $table->decimal('departure_lng', 10, 7)->nullable()->after('departure_lat');
        });

        Schema::table('drivers', function (Blueprint $table) {
            $table->string('departure_address')->nullable()->after('city_id');
            $table->decimal('departure_lat', 10, 7)->nullable()->after('departure_address');
            $table->decimal('departure_lng', 10, 7)->nullable()->after('departure_lat');
        });

        \App\Models\Driver::query()
            ->whereNull('departure_lat')
            ->whereNotNull('current_lat')
            ->whereNotNull('current_lng')
            ->each(function (\App\Models\Driver $driver): void {
                $driver->update([
                    'departure_lat' => $driver->current_lat,
                    'departure_lng' => $driver->current_lng,
                ]);
            });

        \App\Models\City::query()
            ->whereNull('departure_lat')
            ->each(function (\App\Models\City $city): void {
                $driver = \App\Models\Driver::query()
                    ->where('city_id', $city->id)
                    ->whereNotNull('departure_lat')
                    ->whereNotNull('departure_lng')
                    ->first();

                if (! $driver) {
                    return;
                }

                $city->update([
                    'departure_lat' => $driver->departure_lat,
                    'departure_lng' => $driver->departure_lng,
                    'departure_address' => $driver->departure_address ?? $city->name,
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropColumn(['departure_address', 'departure_lat', 'departure_lng']);
        });

        Schema::table('cities', function (Blueprint $table) {
            $table->dropColumn(['departure_address', 'departure_lat', 'departure_lng']);
        });
    }
};
