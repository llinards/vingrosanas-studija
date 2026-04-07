<?php

namespace Database\Factories;

use App\Models\Coach;
use App\Models\UnavailableDate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<UnavailableDate>
 */
class UnavailableDateFactory extends Factory
{
    /**
     * Define the model's default state (studio-wide, single day).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $date = fake()->dateTimeBetween('+1 day', '+3 months')->format('Y-m-d');

        return [
            'coach_id' => null,
            'start_date' => $date,
            'end_date' => $date,
        ];
    }

    /**
     * Indicate this is a per-coach unavailable date.
     */
    public function forCoach(Coach $coach): static
    {
        return $this->state(fn (array $attributes) => [
            'coach_id' => $coach->id,
        ]);
    }

    /**
     * Set a specific date range.
     */
    public function range(Carbon $start, Carbon $end): static
    {
        return $this->state(fn (array $attributes) => [
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
        ]);
    }
}
