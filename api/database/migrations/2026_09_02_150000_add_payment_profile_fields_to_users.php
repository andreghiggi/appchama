<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('cpf', 11)->nullable()->after('email');
            $table->string('home_address_number', 20)->nullable()->after('home_address');
            $table->string('home_postal_code', 8)->nullable()->after('home_address_number');
            $table->string('home_city', 80)->nullable()->after('home_postal_code');
            $table->string('home_state', 2)->nullable()->after('home_city');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'cpf',
                'home_address_number',
                'home_postal_code',
                'home_city',
                'home_state',
            ]);
        });
    }
};
