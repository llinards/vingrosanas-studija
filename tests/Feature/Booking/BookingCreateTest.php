<?php

use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Schedule;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('booking create page can be rendered', function () {
    $this->get(route('booking-create'))
        ->assertSuccessful()
        ->assertSeeLivewire('booking.booking-create');
});

test('booking create page requires authentication', function () {
    auth()->logout();

    $this->get(route('booking-create'))
        ->assertRedirect(route('login'));
});

test('can create a booking with valid data', function () {
    $schedule = Schedule::factory()->create(['is_active' => true]);

    Livewire::test('booking.booking-create')
        ->set('service_type_id', $schedule->service->service_type_id)
        ->set('service_id', $schedule->service_id)
        ->set('schedule_id', $schedule->id)
        ->set('booking_date', '2026-03-15')
        ->set('name', 'Jānis')
        ->set('surname', 'Bērziņš')
        ->set('phone', '+37120000000')
        ->set('email', 'janis@example.com')
        ->set('payment_status', 'paid')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('booking-list'));

    $this->assertDatabaseHas('bookings', [
        'schedule_id' => $schedule->id,
        'name' => 'Jānis',
        'surname' => 'Bērziņš',
        'phone' => '+37120000000',
        'email' => 'janis@example.com',
        'payment_status' => PaymentStatus::Paid->value,
    ]);

    $booking = Booking::latest()->first();
    expect($booking->booking_date->format('Y-m-d'))->toBe('2026-03-15');
});

test('service is required', function () {
    Livewire::test('booking.booking-create')
        ->set('service_id', null)
        ->call('save')
        ->assertHasErrors(['service_id' => 'required']);
});

test('schedule is required', function () {
    Livewire::test('booking.booking-create')
        ->set('schedule_id', null)
        ->call('save')
        ->assertHasErrors(['schedule_id' => 'required']);
});

test('booking date is required', function () {
    Livewire::test('booking.booking-create')
        ->set('booking_date', null)
        ->call('save')
        ->assertHasErrors(['booking_date' => 'required']);
});

test('name is required', function () {
    Livewire::test('booking.booking-create')
        ->set('name', '')
        ->call('save')
        ->assertHasErrors(['name' => 'required']);
});

test('surname is required', function () {
    Livewire::test('booking.booking-create')
        ->set('surname', '')
        ->call('save')
        ->assertHasErrors(['surname' => 'required']);
});

test('phone is required', function () {
    Livewire::test('booking.booking-create')
        ->set('phone', '')
        ->call('save')
        ->assertHasErrors(['phone' => 'required']);
});

test('email is required', function () {
    Livewire::test('booking.booking-create')
        ->set('email', '')
        ->call('save')
        ->assertHasErrors(['email' => 'required']);
});

test('email must be valid', function () {
    Livewire::test('booking.booking-create')
        ->set('email', 'not-an-email')
        ->call('save')
        ->assertHasErrors(['email' => 'email']);
});

test('validation messages are in latvian', function () {
    Livewire::test('booking.booking-create')
        ->set('name', '')
        ->call('save')
        ->assertSee('Vārds ir obligāts.');
});
