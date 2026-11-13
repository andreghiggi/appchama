<?php

use App\Models\City;
use App\Models\Driver;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $city = City::query()->where('name', 'Guaporé')->first();

        if (! $city) {
            return;
        }

        $city->update([
            'departure_address' => 'Centro, Guaporé - RS',
            'departure_lat' => -28.8436,
            'departure_lng' => -51.8908,
        ]);

        Driver::query()
            ->where('city_id', $city->id)
            ->update([
                'departure_address' => 'Centro, Guaporé - RS',
                'departure_lat' => -28.8436,
                'departure_lng' => -51.8908,
            ]);
    }

    public function down(): void
    {
        // dados de produção — não reverte coordenadas
    }
};
