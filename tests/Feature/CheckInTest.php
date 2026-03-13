<?php

use App\Enums\AttendanceStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Schedule;
use Livewire\Livewire;

test('check-in page renders successfully', function () {
    $this->get(route('check-in'))
        ->assertSuccessful()
        ->assertSee('Reģistrācija treniņam');
});

test('check-in requires email', function () {
    Livewire::test('check-in')
        ->call('checkIn')
        ->assertHasErrors(['email' => 'required']);
});

test('check-in validates email format', function () {
    Livewire::test('check-in')
        ->set('email', 'not-an-email')
        ->call('checkIn')
        ->assertHasErrors(['email' => 'email']);
});

test('check-in shows error when no booking found for today', function () {
    Livewire::test('check-in')
        ->set('email', 'test@example.com')
        ->call('checkIn')
        ->assertHasErrors('email');
});

test('check-in marks single matching booking as attended', function () {
    $schedule = Schedule::factory()->create(['start_time' => '10:00']);

    $booking = Booking::factory()->paid()->create([
        'schedule_id' => $schedule->id,
        'booking_date' => today()->toDateString(),
        'email' => 'test@example.com',
    ]);

    Livewire::test('check-in')
        ->set('email', 'test@example.com')
        ->call('checkIn')
        ->assertSet('checkedIn', true)
        ->assertSet('checkedInServiceName', $booking->schedule->service->name);

    expect($booking->fresh()->attendance_status)->toBe(AttendanceStatus::Attended);
});

test('check-in ignores unpaid bookings', function () {
    $schedule = Schedule::factory()->create(['start_time' => '10:00']);

    Booking::factory()->create([
        'schedule_id' => $schedule->id,
        'booking_date' => today()->toDateString(),
        'email' => 'test@example.com',
        'payment_status' => PaymentStatus::Pending,
    ]);

    Livewire::test('check-in')
        ->set('email', 'test@example.com')
        ->call('checkIn')
        ->assertHasErrors('email');
});

test('check-in shows already registered error for attended bookings', function () {
    $schedule = Schedule::factory()->create(['start_time' => '10:00']);

    Booking::factory()->paid()->attended()->create([
        'schedule_id' => $schedule->id,
        'booking_date' => today()->toDateString(),
        'email' => 'test@example.com',
    ]);

    Livewire::test('check-in')
        ->set('email', 'test@example.com')
        ->call('checkIn')
        ->assertHasErrors('email')
        ->assertSee('Jūs jau esat reģistrējies šodienas treniņam.');
});

test('check-in ignores bookings on different dates', function () {
    $schedule = Schedule::factory()->create(['start_time' => '10:00']);

    Booking::factory()->paid()->create([
        'schedule_id' => $schedule->id,
        'booking_date' => today()->addDay()->toDateString(),
        'email' => 'test@example.com',
    ]);

    Livewire::test('check-in')
        ->set('email', 'test@example.com')
        ->call('checkIn')
        ->assertHasErrors('email');
});

test('check-in shows disambiguation when multiple bookings match', function () {
    $schedule1 = Schedule::factory()->create(['start_time' => '10:00']);
    $schedule2 = Schedule::factory()->create(['start_time' => '14:00']);

    Booking::factory()->paid()->create([
        'schedule_id' => $schedule1->id,
        'booking_date' => today()->toDateString(),
        'email' => 'test@example.com',
    ]);

    Booking::factory()->paid()->create([
        'schedule_id' => $schedule2->id,
        'booking_date' => today()->toDateString(),
        'email' => 'test@example.com',
    ]);

    $component = Livewire::test('check-in')
        ->set('email', 'test@example.com')
        ->call('checkIn')
        ->assertSet('checkedIn', false);

    expect($component->instance()->matchingBookings)->toHaveCount(2);
});

test('check-in select from disambiguation list marks booking as attended', function () {
    $schedule1 = Schedule::factory()->create(['start_time' => '10:00']);
    $schedule2 = Schedule::factory()->create(['start_time' => '14:00']);

    $booking1 = Booking::factory()->paid()->create([
        'schedule_id' => $schedule1->id,
        'booking_date' => today()->toDateString(),
        'email' => 'test@example.com',
    ]);

    $booking2 = Booking::factory()->paid()->create([
        'schedule_id' => $schedule2->id,
        'booking_date' => today()->toDateString(),
        'email' => 'test@example.com',
    ]);

    Livewire::test('check-in')
        ->set('email', 'test@example.com')
        ->call('checkIn')
        ->call('selectBooking', $booking2->id)
        ->assertSet('checkedIn', true)
        ->assertSet('checkedInServiceName', $booking2->schedule->service->name);

    expect($booking1->fresh()->attendance_status)->toBe(AttendanceStatus::Pending);
    expect($booking2->fresh()->attendance_status)->toBe(AttendanceStatus::Attended);
});

test('check-in reset form returns to initial state', function () {
    $schedule = Schedule::factory()->create(['start_time' => '10:00']);

    Booking::factory()->paid()->create([
        'schedule_id' => $schedule->id,
        'booking_date' => today()->toDateString(),
        'email' => 'test@example.com',
    ]);

    Livewire::test('check-in')
        ->set('email', 'test@example.com')
        ->call('checkIn')
        ->assertSet('checkedIn', true)
        ->call('resetForm')
        ->assertSet('checkedIn', false)
        ->assertSet('email', '')
        ->assertSet('checkedInServiceName', null)
        ->assertSet('matchingBookings', null);
});
