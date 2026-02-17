<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ServicePriceTier>
 */
class ServicePriceTierFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service_id' => Service::factory(),
            'participant_count' => 1,
            'price' => fake()->numberBetween(1000, 5000),
        ];
    }

    /**
     * Create a tier for a specific participant count.
     */
    public function forParticipants(int $count): static
    {
        return $this->state(fn (array $attributes) => [
            'participant_count' => $count,
        ]);
    }
}
