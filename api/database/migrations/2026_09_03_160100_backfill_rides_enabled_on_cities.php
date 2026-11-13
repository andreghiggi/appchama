<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('cities')
            ->where('active', true)
            ->whereNotNull('departure_lat')
            ->whereNotNull('departure_lng')
            ->update(['rides_enabled' => true]);
    }

    public function down(): void
    {
        DB::table('cities')->update(['rides_enabled' => false]);
    }
};
