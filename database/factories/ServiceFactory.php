<?php

namespace Database\Factories;

use App\Models\Coach;
use App\Models\Service;
use App\Models\ServiceType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
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
            'is_exclusive' => false,
            'is_membership_eligible' => false,
            'position' => 0,
        ];
    }

    /**
     * Mark the service as exclusive (one booking per slot).
     */
    public function exclusive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_exclusive' => true,
        ]);
    }

    /**
     * Mark the service as eligible for membership bookings.
     */
    public function membershipEligible(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_membership_eligible' => true,
        ]);
    }
}
