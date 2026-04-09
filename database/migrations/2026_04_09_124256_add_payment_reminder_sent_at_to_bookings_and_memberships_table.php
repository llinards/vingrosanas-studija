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
            $table->timestamp('payment_reminder_sent_at')->nullable()->after('expires_at');
        });

        Schema::table('memberships', function (Blueprint $table) {
            $table->timestamp('payment_reminder_sent_at')->nullable()->after('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('payment_reminder_sent_at');
        });

        Schema::table('memberships', function (Blueprint $table) {
            $table->dropColumn('payment_reminder_sent_at');
        });
    }
};
