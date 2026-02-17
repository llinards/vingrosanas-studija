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

test('booking edit page can be rendered', function () {
    $booking = Booking::factory()->create();

    $this->get(route('admin.bookings.edit', $booking))
        ->assertSuccessful()
        ->assertSeeLivewire('booking.booking-edit');
});

test('booking edit page requires authentication', function () {
    $booking = Booking::factory()->create();

    auth()->logout();

    $this->get(route('admin.bookings.edit', $booking))
        ->assertRedirect(route('login'));
});

test('booking edit form is populated with booking data', function () {
    $booking = Booking::factory()->create([
        'name' => 'Jānis',
        'surname' => 'Bērziņš',
        'phone' => '+37120000000',
        'email' => 'janis@example.com',
        'payment_status' => PaymentStatus::Paid,
    ]);

    Livewire::test('booking.booking-edit', ['booking' => $booking])
        ->assertSet('name', 'Jānis')
        ->assertSet('surname', 'Bērziņš')
        ->assertSet('phone', '+37120000000')
        ->assertSet('email', 'janis@example.com')
        ->assertSet('payment_status', PaymentStatus::Paid->value)
        ->assertSet('schedule_id', $booking->schedule_id);
});

test('can update a booking', function () {
    $booking = Booking::factory()->create();
    $newSchedule = Schedule::factory()->create(['is_active' => true]);

    Livewire::test('booking.booking-edit', ['booking' => $booking])
        ->set('service_type_id', $newSchedule->service->service_type_id)
        ->set('service_id', $newSchedule->service_id)
        ->set('schedule_id', $newSchedule->id)
        ->set('booking_date', '2026-04-01')
        ->set('name', 'Anna')
        ->set('surname', 'Ozola')
        ->set('phone', '+37129000000')
        ->set('email', 'anna@example.com')
        ->set('payment_status', 'paid')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.bookings.index'));

    $this->assertDatabaseHas('bookings', [
        'id' => $booking->id,
        'schedule_id' => $newSchedule->id,
        'name' => 'Anna',
        'surname' => 'Ozola',
        'payment_status' => PaymentStatus::Paid->value,
    ]);
});

test('can update payment status', function () {
    $booking = Booking::factory()->create(['payment_status' => PaymentStatus::Pending]);

    Livewire::test('booking.booking-edit', ['booking' => $booking])
        ->set('payment_status', 'paid')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.bookings.index'));

    $this->assertDatabaseHas('bookings', [
        'id' => $booking->id,
        'payment_status' => PaymentStatus::Paid->value,
    ]);
});

test('name is required', function () {
    $booking = Booking::factory()->create();

    Livewire::test('booking.booking-edit', ['booking' => $booking])
        ->set('name', '')
        ->call('save')
        ->assertHasErrors(['name' => 'required']);
});

test('email must be valid', function () {
    $booking = Booking::factory()->create();

    Livewire::test('booking.booking-edit', ['booking' => $booking])
        ->set('email', 'not-an-email')
        ->call('save')
        ->assertHasErrors(['email' => 'email']);
});

test('validation messages are in latvian', function () {
    $booking = Booking::factory()->create();

    Livewire::test('booking.booking-edit', ['booking' => $booking])
        ->set('name', '')
        ->call('save')
        ->assertSee('Vārds ir obligāts.');
});
