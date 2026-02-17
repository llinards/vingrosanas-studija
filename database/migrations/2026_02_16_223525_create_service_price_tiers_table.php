<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('service_price_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('participant_count');
            $table->unsignedInteger('price');
            $table->timestamps();

            $table->unique(['service_id', 'participant_count']);
        });

        // Migrate existing service prices to price tiers (1 participant)
        DB::table('services')->orderBy('id')->each(function ($service) {
            DB::table('service_price_tiers')->insert([
                'service_id' => $service->id,
                'participant_count' => 1,
                'price' => $service->price,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_price_tiers');
    }
};
