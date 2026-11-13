<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table("tenants", function (Blueprint $table) {
            $table->string("tagline", 160)->nullable()->after("name");
            $table->json("theme")->nullable()->after("primary_color");
            $table->json("contacts")->nullable()->after("logo_url");
            $table->json("legal")->nullable()->after("contacts");
            $table->json("features")->nullable()->after("legal");
        });
    }

    public function down(): void
    {
        Schema::table("tenants", function (Blueprint $table) {
            $table->dropColumn(["tagline", "theme", "contacts", "legal", "features"]);
        });
    }
};
