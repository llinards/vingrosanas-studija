<?php

namespace Database\Factories;

use App\Enums\DayOfWeek;
use App\Models\Schedule;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Schedule>
 */
class ScheduleFactory extends Factory
{
    /**
     * Define the model's default state (recurring).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service_id' => Service::factory(),
            'day_of_week' => fake()->randomElement(DayOfWeek::cases())->value,
            'date' => null,
            'start_time' => fake()->time('H:i'),
            'max_capacity' => fake()->numberBetween(4, 20),
            'is_active' => true,
        ];
    }

    /**
     * Indicate a specific date schedule.
     */
    public function specificDate(): static
    {
        return $this->state(fn (array $attributes) => [
            'day_of_week' => null,
            'date' => fake()->dateTimeBetween('+1 day', '+3 months')->format('Y-m-d'),
        ]);
    }
}
