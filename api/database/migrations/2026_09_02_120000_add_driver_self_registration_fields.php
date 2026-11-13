<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->string('registration_source', 10)->default('admin')->after('registration_validated_at');
            $table->string('cnh_document_path')->nullable()->after('cnh_expiry');
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropColumn(['registration_source', 'cnh_document_path']);
        });
    }
};
