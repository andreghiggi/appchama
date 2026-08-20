<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drivers', function (Blueprint $table) {
            $table->foreignUuid('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('city_id')->constrained('cities')->cascadeOnDelete();
            $table->string('cnh_number', 30);
            $table->date('cnh_expiry');
            $table->enum('background_check_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->boolean('is_female_driver')->default(false);
            $table->boolean('online')->default(false);
            $table->decimal('current_lat', 10, 7)->nullable();
            $table->decimal('current_lng', 10, 7)->nullable();
            $table->timestamp('last_location_at')->nullable();
            $table->enum('subscription_status', ['active', 'overdue', 'suspended', 'pending'])->default('pending');
            $table->decimal('rating_avg', 3, 2)->default(5.00);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drivers');
    }
};
