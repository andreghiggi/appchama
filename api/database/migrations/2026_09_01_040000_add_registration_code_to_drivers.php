<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->string('registration_code', 12)->nullable()->unique()->after('user_id');
            $table->timestamp('registration_code_confirmed_at')->nullable()->after('registration_code');
            $table->timestamp('registration_validated_at')->nullable()->after('registration_code_confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropColumn([
                'registration_code',
                'registration_code_confirmed_at',
                'registration_validated_at',
            ]);
        });
    }
};
