<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE subscriptions MODIFY status ENUM('pending','active','overdue','canceled') NOT NULL DEFAULT 'active'");
    }

    public function down(): void
    {
        DB::table('subscriptions')->where('status', 'pending')->update(['status' => 'active']);
        DB::statement("ALTER TABLE subscriptions MODIFY status ENUM('active','overdue','canceled') NOT NULL DEFAULT 'active'");
    }
};
