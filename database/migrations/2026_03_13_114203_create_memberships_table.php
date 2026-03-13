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
        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('name');
            $table->string('surname');
            $table->string('phone');
            $table->string('tier');
            $table->unsignedInteger('price');
            $table->unsignedTinyInteger('sessions_total');
            $table->date('period_start');
            $table->date('period_end');
            $table->string('payment_status')->default('pending');
            $table->string('payment_reference')->nullable();
            $table->string('refund_reference')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->string('stripe_checkout_session_id')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('email');
            $table->index(['email', 'period_start', 'period_end']);
            $table->index('stripe_checkout_session_id');
            $table->index('payment_reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memberships');
    }
};
