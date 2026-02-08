<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Coach>
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
            'email' => fake()->boolean(70) ? fake()->unique()->safeEmail() : null,
            'phone' => fake()->boolean(70) ? fake()->unique()->phoneNumber() : null,
            'title' => fake()->jobTitle(),
            'image' => fake()->imageUrl(640, 640, 'people'),
            'bio' => fake()->sentence(12),
            'is_active' => fake()->boolean(85),
            'position' => 0,
        ];
    }
}
