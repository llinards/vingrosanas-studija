<?php

namespace Database\Factories;

use App\Enums\MembershipTier;
use App\Enums\PaymentStatus;
use App\Models\Membership;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Membership>
 */
class MembershipFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tier = fake()->randomElement(MembershipTier::cases());

        return [
            'email' => fake()->safeEmail(),
            'name' => fake()->firstName(),
            'surname' => fake()->lastName(),
            'phone' => fake()->phoneNumber(),
            'tier' => $tier,
            'price' => config("membership.tiers.{$tier->value}.price"),
            'sessions_total' => $tier->sessionCount(),
            'period_start' => today()->startOfMonth(),
            'period_end' => today()->endOfMonth(),
            'payment_status' => PaymentStatus::Pending,
        ];
    }

    /**
     * Indicate a paid membership.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_status' => PaymentStatus::Paid,
            'payment_reference' => 'pi_test_'.fake()->unique()->word(),
        ]);
    }

    /**
     * Set the membership tier to four sessions.
     */
    public function fourSessions(): static
    {
        return $this->state(fn (array $attributes) => [
            'tier' => MembershipTier::FourSessions,
            'sessions_total' => 4,
            'price' => config('membership.tiers.four_sessions.price'),
        ]);
    }

    /**
     * Set the membership tier to nine sessions.
     */
    public function nineSessions(): static
    {
        return $this->state(fn (array $attributes) => [
            'tier' => MembershipTier::NineSessions,
            'sessions_total' => 9,
            'price' => config('membership.tiers.nine_sessions.price'),
        ]);
    }

    /**
     * Indicate an expired membership (past period).
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'period_start' => today()->subMonth()->startOfMonth(),
            'period_end' => today()->subMonth()->endOfMonth(),
        ]);
    }

    /**
     * Indicate a refunded membership.
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
}
