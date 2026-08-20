<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('driver_id')->constrained('drivers', 'user_id')->cascadeOnDelete();
            $table->string('plan_name', 60);
            $table->decimal('amount', 10, 2);
            $table->unsignedTinyInteger('due_day');
            $table->enum('status', ['active', 'overdue', 'canceled'])->default('active');
            $table->date('next_charge_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
