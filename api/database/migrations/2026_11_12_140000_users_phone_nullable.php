<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Administradores do painel entram por e-mail e senha: telefone passa a ser opcional.
        DB::statement('ALTER TABLE users MODIFY phone VARCHAR(20) NULL');
    }

    public function down(): void
    {
        DB::statement("UPDATE users SET phone = CONCAT('adm', SUBSTRING(REPLACE(id, '-', ''), 1, 16)) WHERE phone IS NULL");
        DB::statement('ALTER TABLE users MODIFY phone VARCHAR(20) NOT NULL');
    }
};
