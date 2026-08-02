<?php

namespace Database\Factories;

use App\Models\Coach;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Coach>
 */
class CoachFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->unique()->phoneNumber(),
            'title' => fake()->jobTitle(),
            'image' => fake()->imageUrl(640, 640, 'people'),
            'bio' => fake()->sentence(12),
            'is_active' => 1,
            'position' => 0,
        ];
    }
}
