<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            // ride | payment | report | account | app | other
            $table->string('category', 20)->default('other')->after('ride_id');
            $table->timestamp('last_message_at')->nullable()->after('status');
            // resposta da equipe que o usuário ainda não viu / mensagem do usuário que a equipe ainda não viu
            $table->boolean('user_unread')->default(false)->after('last_message_at');
            $table->boolean('staff_unread')->default(true)->after('user_unread');
            $table->index(['tenant_id', 'status', 'last_message_at']);
        });

        Schema::create('support_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('support_ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('author', ['user', 'staff']);
            $table->text('body');
            $table->timestamps();
            $table->index(['support_ticket_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_messages');
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'status', 'last_message_at']);
            $table->dropColumn(['category', 'last_message_at', 'user_unread', 'staff_unread']);
        });
    }
};
