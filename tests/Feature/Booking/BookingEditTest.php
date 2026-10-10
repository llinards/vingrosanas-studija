<?php

use App\Enums\PaymentStatus;
use App\Mail\BookingRescheduled;
use App\Models\Booking;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
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
        ->set('booking_date', now()->addDay()->toDateString())
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

test('rescheduled email is sent when schedule changes', function () {
    Mail::fake();

    $booking = Booking::factory()->create(['email' => 'client@example.com']);
    $newSchedule = Schedule::factory()->create(['is_active' => true]);

    Livewire::test('booking.booking-edit', ['booking' => $booking])
        ->set('service_type_id', $newSchedule->service->service_type_id)
        ->set('service_id', $newSchedule->service_id)
        ->set('schedule_id', $newSchedule->id)
        ->set('booking_date', $booking->booking_date->format('Y-m-d'))
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.bookings.index'));

    Mail::assertQueued(BookingRescheduled::class, function ($mail) use ($booking) {
        return $mail->hasTo('client@example.com')
            && $mail->originalDate->equalTo($booking->booking_date)
            && $mail->originalTime === $booking->schedule->start_time;
    });
});

test('rescheduled email is sent when booking date changes', function () {
    Mail::fake();

    $booking = Booking::factory()->create(['email' => 'client@example.com']);
    $originalDate = $booking->booking_date->copy();

    Livewire::test('booking.booking-edit', ['booking' => $booking])
        ->set('booking_date', now()->addWeek()->toDateString())
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.bookings.index'));

    Mail::assertQueued(BookingRescheduled::class, function ($mail) use ($originalDate) {
        return $mail->hasTo('client@example.com')
            && $mail->originalDate->equalTo($originalDate);
    });
});

test('rescheduled email is not sent when schedule and date remain unchanged', function () {
    Mail::fake();

    $booking = Booking::factory()->create();

    Livewire::test('booking.booking-edit', ['booking' => $booking])
        ->set('name', 'Updated Name')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.bookings.index'));

    Mail::assertNotQueued(BookingRescheduled::class);
});

test('shows how many people are signed up and how many places are free, counting the edited booking', function () {
    $schedule = Schedule::factory()->create(['max_capacity' => 12]);
    $booking = Booking::factory()->paid()->create(['schedule_id' => $schedule->id, 'booking_date' => '2026-12-02']);

    Livewire::test('booking.booking-edit', ['booking' => $booking])
        ->assertSee('Pieteikušies: 1 no 12 · Brīvas vietas: 11')
        ->assertDontSee('Atlikušās vietas');
});

test('counts the participants of every booking of that class and date as signed up', function () {
    $schedule = Schedule::factory()->create(['max_capacity' => 12]);
    $booking = Booking::factory()->paid()->create(['schedule_id' => $schedule->id, 'booking_date' => '2026-12-02']);
    Booking::factory()->paid()->withParticipants(2)->create(['schedule_id' => $schedule->id, 'booking_date' => '2026-12-02']);
    Booking::factory()->paid()->create(['schedule_id' => $schedule->id, 'booking_date' => '2026-12-09']);

    Livewire::test('booking.booking-edit', ['booking' => $booking])
        ->assertSee('Pieteikušies: 3 no 12 · Brīvas vietas: 9');
});

test('does not count a booking that no longer holds a place as signed up', function (array $otherBooking) {
    $schedule = Schedule::factory()->create(['max_capacity' => 12]);
    $booking = Booking::factory()->paid()->create(['schedule_id' => $schedule->id, 'booking_date' => '2026-12-02']);
    Booking::factory()->create(array_merge(['schedule_id' => $schedule->id, 'booking_date' => '2026-12-02'], $otherBooking));

    Livewire::test('booking.booking-edit', ['booking' => $booking])
        ->assertSee('Pieteikušies: 1 no 12 · Brīvas vietas: 11');
})->with([
    'refunded' => [['payment_status' => PaymentStatus::Refunded]],
    'failed' => [['payment_status' => PaymentStatus::Failed]],
    'unpaid and expired' => [['payment_status' => PaymentStatus::Pending, 'expires_at' => '2020-01-01 00:00:00']],
]);

test('shows the sign-ups of the newly chosen date when the date is changed', function () {
    $schedule = Schedule::factory()->create(['max_capacity' => 12]);
    $booking = Booking::factory()->paid()->create(['schedule_id' => $schedule->id, 'booking_date' => '2026-12-02']);

    Livewire::test('booking.booking-edit', ['booking' => $booking])
        ->set('booking_date', '2026-12-09')
        ->assertSee('Pieteikušies: 0 no 12 · Brīvas vietas: 12');
});

test('does not show free places as a negative number when the class is overbooked', function () {
    $schedule = Schedule::factory()->create(['max_capacity' => 2]);
    $booking = Booking::factory()->paid()->withParticipants(3)->create(['schedule_id' => $schedule->id, 'booking_date' => '2026-12-02']);

    Livewire::test('booking.booking-edit', ['booking' => $booking])
        ->assertSee('Pieteikušies: 3 no 2 · Brīvas vietas: 0');
});

test('does not show sign-ups for an individual session', function () {
    $service = Service::factory()->exclusive()->create();
    $schedule = Schedule::factory()->create(['service_id' => $service->id, 'max_capacity' => 3]);
    $booking = Booking::factory()->paid()->create(['schedule_id' => $schedule->id, 'booking_date' => '2026-12-02']);

    Livewire::test('booking.booking-edit', ['booking' => $booking])
        ->assertDontSee('Pieteikušies')
        ->assertDontSee('Brīvas vietas');
});
