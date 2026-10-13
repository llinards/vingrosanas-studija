<?php

use App\Enums\AttendanceStatus;
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
    $newSchedule = Schedule::factory()->create(['is_active' => true, 'day_of_week' => now()->addDay()->dayOfWeekIso]);

    Livewire::test('booking.booking-edit', ['booking' => $booking])
        ->set('coach_id', $newSchedule->service->coach_id)
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
    $newSchedule = Schedule::factory()->create(['is_active' => true, 'day_of_week' => $booking->booking_date->dayOfWeekIso]);

    Livewire::test('booking.booking-edit', ['booking' => $booking])
        ->set('coach_id', $newSchedule->service->coach_id)
        ->set('schedule_id', $newSchedule->id)
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

    $booking = Booking::factory()->create([
        'email' => 'client@example.com',
        'booking_date' => now()->addDays(3)->toDateString(),
        'schedule_id' => Schedule::factory()->create(['day_of_week' => now()->addWeek()->dayOfWeekIso])->id,
    ]);
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
    $schedule = Schedule::factory()->create(['day_of_week' => 3, 'max_capacity' => 12]);
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

test('saves corrections to a booking whose date has passed', function () {
    Mail::fake();
    $this->travelTo('2026-10-10 12:00:00');
    $booking = Booking::factory()->paid()->create(['booking_date' => '2026-10-08', 'phone' => '+371 20000000']);

    Livewire::test('booking.booking-edit', ['booking' => $booking])
        ->set('attendance_status', 'missed')
        ->set('payment_status', 'refunded')
        ->set('phone', '+371 29999999')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.bookings.index'));

    expect($booking->fresh())
        ->attendance_status->toBe(AttendanceStatus::Missed)
        ->payment_status->toBe(PaymentStatus::Refunded)
        ->phone->toBe('+371 29999999');
    expect($booking->fresh()->booking_date->toDateString())->toBe('2026-10-08');
    Mail::assertNotQueued(BookingRescheduled::class);
});

test('refuses to move a booking onto a date that has passed', function (string $currentDate) {
    Mail::fake();
    $this->travelTo('2026-10-10 12:00:00');
    $booking = Booking::factory()->paid()->create(['booking_date' => $currentDate]);

    Livewire::test('booking.booking-edit', ['booking' => $booking])
        ->set('booking_date', '2026-10-07')
        ->call('save')
        ->assertHasErrors(['booking_date' => 'after_or_equal'])
        ->assertSee('Datumam jābūt šodienai vai nākotnē.')
        ->assertNoRedirect();

    expect($booking->fresh()->booking_date->toDateString())->toBe($currentDate);
    Mail::assertNotQueued(BookingRescheduled::class);
})->with([
    'from another past date' => ['2026-10-08'],
    'from a future date' => ['2026-10-20'],
]);

test('moves a past booking to a future date and tells the customer', function () {
    Mail::fake();
    $this->travelTo('2026-10-10 12:00:00');
    $booking = Booking::factory()->paid()->create([
        'booking_date' => '2026-10-08',
        'email' => 'client@example.com',
        'schedule_id' => Schedule::factory()->create(['day_of_week' => 6])->id,
    ]);

    Livewire::test('booking.booking-edit', ['booking' => $booking])
        ->set('booking_date', '2026-10-17')
        ->call('save')
        ->assertHasNoErrors();

    expect($booking->fresh()->booking_date->toDateString())->toBe('2026-10-17');
    Mail::assertQueued(BookingRescheduled::class, fn ($mail) => $mail->hasTo('client@example.com'));
});

test('does not warn that the class is full because of the booking being edited', function () {
    $schedule = Schedule::factory()->create(['day_of_week' => 3, 'max_capacity' => 1]);
    $booking = Booking::factory()->paid()->create(['schedule_id' => $schedule->id, 'booking_date' => '2026-12-02']);

    Livewire::test('booking.booking-edit', ['booking' => $booking])
        ->assertDontSee('Nodarbība šajā datumā ir pilna.');
});

test('warns on the edit screen when other bookings already fill the class', function () {
    $schedule = Schedule::factory()->create(['day_of_week' => 3, 'max_capacity' => 1]);
    $booking = Booking::factory()->paid()->create(['schedule_id' => $schedule->id, 'booking_date' => '2026-12-02']);
    Booking::factory()->paid()->create(['schedule_id' => $schedule->id, 'booking_date' => '2026-12-02']);

    Livewire::test('booking.booking-edit', ['booking' => $booking])
        ->assertSee('Nodarbība šajā datumā ir pilna.');
});

test('still offers the class of a booking after that class was switched off', function () {
    $schedule = Schedule::factory()->create([
        'service_id' => Service::factory()->create(['name' => 'Slēgtā nodarbība'])->id,
        'day_of_week' => 3,
        'is_active' => false,
    ]);
    $booking = Booking::factory()->paid()->create(['schedule_id' => $schedule->id, 'booking_date' => '2026-12-02']);

    Livewire::test('booking.booking-edit', ['booking' => $booking])
        ->assertSet('schedule_id', $schedule->id)
        ->assertSee('Slēgtā nodarbība')
        ->set('phone', '+371 29999999')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.bookings.index'));
});

test('keeps and warns about a booking that sits on a day its class does not run', function () {
    $schedule = Schedule::factory()->create([
        'service_id' => Service::factory()->create(['name' => 'Trešdienas nodarbība'])->id,
        'day_of_week' => 3,
    ]);
    $booking = Booking::factory()->paid()->create(['schedule_id' => $schedule->id, 'booking_date' => '2026-12-03']);

    Livewire::test('booking.booking-edit', ['booking' => $booking])
        ->assertSet('schedule_id', $schedule->id)
        ->assertSee('Trešdienas nodarbība')
        ->assertSee('Šī nodarbība šajā nedēļas dienā nenotiek.')
        ->set('phone', '+371 29999999')
        ->call('save')
        ->assertHasNoErrors();
});

test('opens with the coach of the booking selected', function () {
    $booking = Booking::factory()->paid()->create();

    Livewire::test('booking.booking-edit', ['booking' => $booking])
        ->assertSet('coach_id', $booking->schedule->service->coach_id)
        ->assertSet('schedule_id', $booking->schedule_id);
});

test('keeps the class when a booking is moved to a day its class does not normally run', function () {
    Mail::fake();
    $this->travelTo('2026-10-05 09:00:00');
    $schedule = Schedule::factory()->create(['day_of_week' => 3]);
    $booking = Booking::factory()->paid()->create(['schedule_id' => $schedule->id, 'booking_date' => '2026-10-07']);

    Livewire::test('booking.booking-edit', ['booking' => $booking])
        ->set('booking_date', '2026-10-09')
        ->assertSet('schedule_id', $schedule->id)
        ->assertSee('Šī nodarbība šajā nedēļas dienā nenotiek.')
        ->call('save')
        ->assertHasNoErrors();

    expect($booking->fresh()->booking_date->toDateString())->toBe('2026-10-09');
    Mail::assertQueued(BookingRescheduled::class);
});

test('still offers the past one-off class of the booking being edited', function () {
    $this->travelTo('2026-10-10 12:00:00');
    $schedule = Schedule::factory()->create([
        'service_id' => Service::factory()->create(['name' => 'Pagājušā meistarklase'])->id,
        'day_of_week' => null,
        'date' => '2026-10-07',
    ]);
    $booking = Booking::factory()->paid()->create(['schedule_id' => $schedule->id, 'booking_date' => '2026-10-07']);

    Livewire::test('booking.booking-edit', ['booking' => $booking])
        ->assertSee('Pagājušā meistarklase')
        ->set('attendance_status', 'attended')
        ->call('save')
        ->assertHasNoErrors();
});
