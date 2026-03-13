<?php

use App\Models\Booking;
use App\Models\Membership;
use App\Models\Schedule;
use Livewire\Livewire;

test('check-in shows membership info for membership booking', function () {
    $membership = Membership::factory()->paid()->fourSessions()->create([
        'email' => 'member@example.com',
        'period_start' => today()->startOfMonth(),
        'period_end' => today()->endOfMonth(),
    ]);

    $schedule = Schedule::factory()->create(['start_time' => '10:00']);

    // Create today's booking
    $booking = Booking::factory()->forMembership($membership)->create([
        'schedule_id' => $schedule->id,
        'booking_date' => today()->toDateString(),
    ]);

    // Create an already attended booking (from previous day)
    Booking::factory()->forMembership($membership)->attended()->create([
        'schedule_id' => $schedule->id,
        'booking_date' => today()->subDay()->toDateString(),
    ]);

    $component = Livewire::test('check-in')
        ->set('email', 'member@example.com')
        ->call('checkIn')
        ->assertSet('checkedIn', true)
        ->assertSet('hasMembership', true)
        ->assertSet('membershipSessionsTotal', 4);

    // After check-in, the current booking is also attended, so 2 total
    $component->assertSet('membershipSessionsUsed', 2);
});

test('check-in does not show membership info for regular booking', function () {
    $schedule = Schedule::factory()->create(['start_time' => '10:00']);

    $booking = Booking::factory()->paid()->create([
        'schedule_id' => $schedule->id,
        'booking_date' => today()->toDateString(),
        'email' => 'regular@example.com',
    ]);

    Livewire::test('check-in')
        ->set('email', 'regular@example.com')
        ->call('checkIn')
        ->assertSet('checkedIn', true)
        ->assertSet('hasMembership', false);
});
