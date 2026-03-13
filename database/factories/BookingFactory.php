<?php

namespace Database\Factories;

use App\Enums\AttendanceStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Membership;
use App\Models\Schedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'schedule_id' => Schedule::factory(),
            'booking_date' => fake()->dateTimeBetween('+1 day', '+4 weeks')->format('Y-m-d'),
            'name' => fake()->firstName(),
            'surname' => fake()->lastName(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->safeEmail(),
            'payment_status' => PaymentStatus::Pending,
            'attendance_status' => AttendanceStatus::Pending,
            'participant_count' => 1,
        ];
    }

    /**
     * Create a booking with a specific number of participants.
     */
    public function withParticipants(int $count): static
    {
        return $this->state(fn (array $attributes) => [
            'participant_count' => $count,
        ]);
    }

    /**
     * Indicate a paid booking.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_status' => PaymentStatus::Paid,
        ]);
    }

    /**
     * Indicate a refunded booking.
     */
    public function refunded(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_status' => PaymentStatus::Refunded,
            'payment_reference' => 'pi_test_'.fake()->unique()->word(),
            'refund_reference' => 're_test_'.fake()->unique()->word(),
            'refunded_at' => now(),
        ]);
    }

    /**
     * Indicate an attended booking.
     */
    public function attended(): static
    {
        return $this->state(fn (array $attributes) => [
            'attendance_status' => AttendanceStatus::Attended,
        ]);
    }

    /**
     * Indicate a missed booking.
     */
    public function missed(): static
    {
        return $this->state(fn (array $attributes) => [
            'attendance_status' => AttendanceStatus::Missed,
        ]);
    }

    /**
     * Indicate a past booking.
     */
    public function past(): static
    {
        return $this->state(fn (array $attributes) => [
            'booking_date' => fake()->dateTimeBetween('-4 weeks', '-1 day')->format('Y-m-d'),
        ]);
    }

    /**
     * Associate the booking with a membership.
     */
    public function forMembership(Membership $membership): static
    {
        return $this->state(fn (array $attributes) => [
            'membership_id' => $membership->id,
            'payment_status' => PaymentStatus::Paid,
            'name' => $membership->name,
            'surname' => $membership->surname,
            'phone' => $membership->phone,
            'email' => $membership->email,
        ]);
    }
}
