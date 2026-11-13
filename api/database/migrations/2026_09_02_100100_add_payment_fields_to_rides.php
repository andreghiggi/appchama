<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE rides MODIFY COLUMN status ENUM(
            'requested', 'searching', 'accepted', 'driver_arrived',
            'in_progress', 'awaiting_payment', 'completed',
            'canceled_by_passenger', 'canceled_by_driver', 'no_drivers_available'
        ) NOT NULL DEFAULT 'requested'");

        Schema::table('rides', function (Blueprint $table) {
            $table->string('payment_status', 20)->default('pending')->after('completed_at');
            $table->string('payment_method', 20)->nullable()->after('payment_status');
            $table->timestamp('paid_at')->nullable()->after('payment_method');
            $table->decimal('platform_fee_amount', 10, 2)->nullable()->after('paid_at');
            $table->decimal('driver_net_amount', 10, 2)->nullable()->after('platform_fee_amount');
        });
    }

    public function down(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->dropColumn([
                'payment_status',
                'payment_method',
                'paid_at',
                'platform_fee_amount',
                'driver_net_amount',
            ]);
        });

        DB::statement("ALTER TABLE rides MODIFY COLUMN status ENUM(
            'requested', 'searching', 'accepted', 'driver_arrived',
            'in_progress', 'completed', 'canceled_by_passenger',
            'canceled_by_driver', 'no_drivers_available'
        ) NOT NULL DEFAULT 'requested'");
    }
};
