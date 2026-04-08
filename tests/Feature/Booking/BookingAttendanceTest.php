<?php

use App\Enums\AttendanceStatus;
use App\Models\Booking;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('attendance status enum has correct labels', function () {
    expect(AttendanceStatus::Pending->label())->toBe('Nav apmeklēts');
    expect(AttendanceStatus::Attended->label())->toBe('Apmeklēts');
    expect(AttendanceStatus::Missed->label())->toBe('Neieradās');
});

test('booking defaults to pending attendance status', function () {
    $booking = Booking::factory()->create();

    expect($booking->attendance_status)->toBe(AttendanceStatus::Pending);
});

test('booking factory attended state works', function () {
    $booking = Booking::factory()->attended()->create();

    expect($booking->attendance_status)->toBe(AttendanceStatus::Attended);
});

test('booking factory missed state works', function () {
    $booking = Booking::factory()->missed()->create();

    expect($booking->attendance_status)->toBe(AttendanceStatus::Missed);
});

test('booking list shows attendance status badge', function () {
    Booking::factory()->create(['attendance_status' => AttendanceStatus::Pending]);

    $this->get(route('admin.bookings.index'))
        ->assertSuccessful()
        ->assertSee('Nav apmeklēts');
});

test('booking list displays attendance status filter checkboxes', function () {
    Booking::factory()->create();

    $this->get(route('admin.bookings.index'))
        ->assertSuccessful()
        ->assertSee('Apmeklēts')
        ->assertSee('Nav apmeklēts')
        ->assertSee('Neieradās');
});

test('booking list filters bookings by attendance status', function () {
    $attendedBooking = Booking::factory()->attended()->create(['booking_date' => today()]);
    Booking::factory()->create(['attendance_status' => AttendanceStatus::Pending, 'booking_date' => today()]);
    Booking::factory()->missed()->create(['booking_date' => today()]);

    $component = Livewire::test('booking.booking-list')
        ->set('attendanceStatus', AttendanceStatus::Attended->value);

    $bookings = $component->instance()->bookings;
    expect($bookings)->toHaveCount(1);
    expect($bookings->first()->id)->toBe($attendedBooking->id);
});

test('booking list attendance status filter is reflected in url query string', function () {
    Livewire::withQueryParams(['attendanceStatus' => AttendanceStatus::Attended->value])
        ->test('booking.booking-list')
        ->assertSet('attendanceStatus', AttendanceStatus::Attended->value);
});

test('booking create form includes attendance status field', function () {
    $this->get(route('admin.bookings.create'))
        ->assertSuccessful()
        ->assertSee('Apmeklējuma statuss');
});

test('booking edit form loads attendance status', function () {
    $booking = Booking::factory()->attended()->create();

    Livewire::test('booking.booking-edit', ['booking' => $booking])
        ->assertSet('attendance_status', AttendanceStatus::Attended->value);
});

test('booking edit form saves attendance status', function () {
    $booking = Booking::factory()->create();

    Livewire::test('booking.booking-edit', ['booking' => $booking])
        ->set('attendance_status', AttendanceStatus::Attended->value)
        ->call('save');

    expect($booking->fresh()->attendance_status)->toBe(AttendanceStatus::Attended);
});
