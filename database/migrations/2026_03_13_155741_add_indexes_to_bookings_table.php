<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->index(['schedule_id', 'booking_date', 'payment_status']);
            $table->index('payment_reference');
            $table->index('stripe_checkout_session_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['schedule_id', 'booking_date', 'payment_status']);
            $table->dropIndex(['payment_reference']);
            $table->dropIndex(['stripe_checkout_session_id']);
        });
    }
};
