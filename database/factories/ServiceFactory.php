<?php

namespace Database\Factories;

use App\Models\Coach;
use App\Models\ServiceType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Service>
 */
class ServiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service_type_id' => ServiceType::factory(),
            'coach_id' => Coach::factory(),
            'name' => fake()->words(3, true),
            'price' => fake()->numberBetween(500, 10000),
            'is_active' => true,
            'position' => 0,
        ];
    }
}
