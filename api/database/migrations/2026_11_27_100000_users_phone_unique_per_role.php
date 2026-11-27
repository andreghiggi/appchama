<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** O mesmo telefone passa a valer para uma conta de passageiro e outra de motorista na mesma marca. */
    public function up(): void
    {
        // O novo indice vem antes: o antigo e o que sustenta a chave estrangeira de tenant_id.
        Schema::table('users', fn (Blueprint $table) => $table->unique(['tenant_id', 'phone', 'role']));
        Schema::table('users', fn (Blueprint $table) => $table->dropUnique(['tenant_id', 'phone']));
    }

    public function down(): void
    {
        // So volta se nao existir telefone repetido entre perfis.
        Schema::table('users', fn (Blueprint $table) => $table->unique(['tenant_id', 'phone']));
        Schema::table('users', fn (Blueprint $table) => $table->dropUnique(['tenant_id', 'phone', 'role']));
    }
};
