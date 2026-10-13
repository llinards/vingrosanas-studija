<?php

use App\Enums\DayOfWeek;
use App\Enums\PaymentStatus;
use App\Mail\BookingRescheduled;
use App\Models\Booking;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('schedule edit page can be rendered', function () {
    $schedule = Schedule::factory()->create();

    $this->get(route('admin.schedules.edit', $schedule))
        ->assertSuccessful()
        ->assertSeeLivewire('schedule.schedule-edit');
});

test('schedule edit page requires authentication', function () {
    $schedule = Schedule::factory()->create();

    auth()->logout();

    $this->get(route('admin.schedules.edit', $schedule))
        ->assertRedirect(route('login'));
});

test('schedule edit form is populated with recurring schedule data', function () {
    $schedule = Schedule::factory()->create([
        'day_of_week' => DayOfWeek::Tuesday->value,
        'start_time' => '10:30',
        'max_capacity' => 8,
        'is_active' => true,
    ]);

    Livewire::test('schedule.schedule-edit', ['schedule' => $schedule])
        ->assertSet('service_id', $schedule->service_id)
        ->assertSet('schedule_type', 'recurring')
        ->assertSet('day_of_week', DayOfWeek::Tuesday->value)
        ->assertSet('start_time', '10:30')
        ->assertSet('max_capacity', '8')
        ->assertSet('is_active', true);
});

test('schedule edit form is populated with specific date schedule data', function () {
    $schedule = Schedule::factory()->specificDate()->create([
        'date' => '2026-03-15',
        'start_time' => '14:00',
        'max_capacity' => 10,
    ]);

    Livewire::test('schedule.schedule-edit', ['schedule' => $schedule])
        ->assertSet('schedule_type', 'specific')
        ->assertSet('date', '2026-03-15')
        ->assertSet('day_of_week', null);
});

test('can update a recurring schedule', function () {
    $schedule = Schedule::factory()->create();
    $newService = Service::factory()->create();

    Livewire::test('schedule.schedule-edit', ['schedule' => $schedule])
        ->set('service_id', $newService->id)
        ->set('schedule_type', 'recurring')
        ->set('day_of_week', DayOfWeek::Friday->value)
        ->set('start_time', '09:00')
        ->set('max_capacity', '15')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.schedules.index'));

    $this->assertDatabaseHas('schedules', [
        'id' => $schedule->id,
        'service_id' => $newService->id,
        'day_of_week' => DayOfWeek::Friday->value,
        'date' => null,
        'start_time' => '09:00',
        'max_capacity' => 15,
    ]);
});

test('can update a schedule from recurring to specific date', function () {
    $schedule = Schedule::factory()->create(['day_of_week' => DayOfWeek::Monday->value]);

    Livewire::test('schedule.schedule-edit', ['schedule' => $schedule])
        ->set('schedule_type', 'specific')
        ->set('date', '2026-04-01')
        ->set('start_time', '16:00')
        ->set('max_capacity', '6')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.schedules.index'));

    $schedule->refresh();
    expect($schedule->day_of_week)->toBeNull()
        ->and($schedule->date->format('Y-m-d'))->toBe('2026-04-01');
});

test('can update a schedule from specific date to recurring', function () {
    $schedule = Schedule::factory()->specificDate()->create(['date' => '2026-03-15']);

    Livewire::test('schedule.schedule-edit', ['schedule' => $schedule])
        ->set('schedule_type', 'recurring')
        ->set('day_of_week', DayOfWeek::Wednesday->value)
        ->set('start_time', '11:00')
        ->set('max_capacity', '10')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.schedules.index'));

    $this->assertDatabaseHas('schedules', [
        'id' => $schedule->id,
        'day_of_week' => DayOfWeek::Wednesday->value,
        'date' => null,
    ]);
});

test('service is required', function () {
    $schedule = Schedule::factory()->create();

    Livewire::test('schedule.schedule-edit', ['schedule' => $schedule])
        ->set('service_id', null)
        ->call('save')
        ->assertHasErrors(['service_id' => 'required']);
});

test('start_time is required', function () {
    $schedule = Schedule::factory()->create();

    Livewire::test('schedule.schedule-edit', ['schedule' => $schedule])
        ->set('start_time', '')
        ->call('save')
        ->assertHasErrors(['start_time' => 'required']);
});

test('max_capacity is required', function () {
    $schedule = Schedule::factory()->create();

    Livewire::test('schedule.schedule-edit', ['schedule' => $schedule])
        ->set('max_capacity', '')
        ->call('save')
        ->assertHasErrors(['max_capacity' => 'required']);
});

test('can toggle is_active on a schedule', function () {
    $schedule = Schedule::factory()->create(['is_active' => true]);

    Livewire::test('schedule.schedule-edit', ['schedule' => $schedule])
        ->set('is_active', false)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.schedules.index'));

    $this->assertDatabaseHas('schedules', [
        'id' => $schedule->id,
        'is_active' => false,
    ]);
});

test('validation messages are in latvian', function () {
    $schedule = Schedule::factory()->create();

    Livewire::test('schedule.schedule-edit', ['schedule' => $schedule])
        ->set('service_id', null)
        ->call('save')
        ->assertSee('Pakalpojums ir obligāts.');
});

/**
 * A weekly class on Wednesdays at 18:00. "Now" in these tests is Monday 5 October 2026, 09:00,
 * so its next two occurrences are 7 and 14 October.
 *
 * @param  array<string, mixed>  $attributes
 */
function createWednesdayClass(array $attributes = []): Schedule
{
    test()->travelTo(Carbon::parse('2026-10-05 09:00:00'));

    return Schedule::factory()->create(array_merge([
        'day_of_week' => DayOfWeek::Wednesday->value,
        'date' => null,
        'start_time' => '18:00',
        'max_capacity' => 10,
    ], $attributes));
}

test('asks for confirmation before a time change moves upcoming bookings', function () {
    Mail::fake();
    $class = createWednesdayClass();
    Booking::factory()->paid()->count(2)->create(['schedule_id' => $class->id, 'booking_date' => '2026-10-07']);

    Livewire::test('schedule.schedule-edit', ['schedule' => $class])
        ->set('start_time', '19:00')
        ->call('save')
        ->assertSet('bookingsToMove', 2)
        ->assertSee('Šai nodarbībai ir 2 nākotnes rezervācijas.')
        ->assertNoRedirect();

    expect(substr((string) $class->fresh()->start_time, 0, 5))->toBe('18:00');
    Mail::assertNothingOutgoing();
});

test('moves the class to its new time and emails each customer once the change is confirmed', function () {
    Mail::fake();
    $class = createWednesdayClass();
    $anna = Booking::factory()->paid()->create(['schedule_id' => $class->id, 'booking_date' => '2026-10-07', 'email' => 'anna@example.com']);
    $janis = Booking::factory()->paid()->create(['schedule_id' => $class->id, 'booking_date' => '2026-10-14', 'email' => 'janis@example.com']);

    Livewire::test('schedule.schedule-edit', ['schedule' => $class])
        ->set('start_time', '19:00')
        ->call('saveAndMoveBookings')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.schedules.index'));

    expect(substr((string) $class->fresh()->start_time, 0, 5))->toBe('19:00');
    expect($anna->fresh()->booking_date->toDateString())->toBe('2026-10-07');
    expect($janis->fresh()->booking_date->toDateString())->toBe('2026-10-14');
    Mail::assertQueued(BookingRescheduled::class, fn (BookingRescheduled $mail) => $mail->hasTo('anna@example.com')
        && $mail->originalDate->toDateString() === '2026-10-07'
        && substr($mail->originalTime, 0, 5) === '18:00');
    Mail::assertQueued(BookingRescheduled::class, fn (BookingRescheduled $mail) => $mail->hasTo('janis@example.com')
        && $mail->originalDate->toDateString() === '2026-10-14');
    Mail::assertQueuedCount(2);
});

test('moves upcoming bookings to the new weekday of the same week', function () {
    Mail::fake();
    $class = createWednesdayClass();
    $booking = Booking::factory()->paid()->create(['schedule_id' => $class->id, 'booking_date' => '2026-10-14', 'email' => 'anna@example.com']);

    Livewire::test('schedule.schedule-edit', ['schedule' => $class])
        ->set('day_of_week', DayOfWeek::Thursday->value)
        ->call('saveAndMoveBookings')
        ->assertHasNoErrors();

    expect($class->fresh()->day_of_week)->toBe(DayOfWeek::Thursday);
    expect($booking->fresh()->booking_date->toDateString())->toBe('2026-10-15');
    Mail::assertQueued(BookingRescheduled::class, fn (BookingRescheduled $mail) => $mail->hasTo('anna@example.com')
        && $mail->originalDate->toDateString() === '2026-10-14'
        && $mail->booking->booking_date->toDateString() === '2026-10-15');
});

test('moves a booking to the following week when the new weekday of its week has already passed', function () {
    Mail::fake();
    $class = createWednesdayClass();
    $this->travelTo(Carbon::parse('2026-10-06 10:00:00'));
    $booking = Booking::factory()->paid()->create(['schedule_id' => $class->id, 'booking_date' => '2026-10-07']);

    Livewire::test('schedule.schedule-edit', ['schedule' => $class])
        ->set('day_of_week', DayOfWeek::Monday->value)
        ->call('saveAndMoveBookings')
        ->assertHasNoErrors();

    expect($booking->fresh()->booking_date->toDateString())->toBe('2026-10-12');
});

test('moves the upcoming bookings of a one-off class to its new date', function () {
    Mail::fake();
    $class = createWednesdayClass(['day_of_week' => null, 'date' => '2026-10-24']);
    $booking = Booking::factory()->paid()->create(['schedule_id' => $class->id, 'booking_date' => '2026-10-24', 'email' => 'anna@example.com']);

    Livewire::test('schedule.schedule-edit', ['schedule' => $class])
        ->set('date', '2026-10-31')
        ->call('saveAndMoveBookings')
        ->assertHasNoErrors();

    expect($booking->fresh()->booking_date->toDateString())->toBe('2026-10-31');
    Mail::assertQueued(BookingRescheduled::class, fn (BookingRescheduled $mail) => $mail->hasTo('anna@example.com')
        && $mail->originalDate->toDateString() === '2026-10-24');
});

test('moves an unpaid booking that still holds its place without emailing', function () {
    Mail::fake();
    $class = createWednesdayClass();
    $booking = Booking::factory()->create(['schedule_id' => $class->id, 'booking_date' => '2026-10-14', 'expires_at' => now()->addMinutes(20)]);

    Livewire::test('schedule.schedule-edit', ['schedule' => $class])
        ->set('day_of_week', DayOfWeek::Thursday->value)
        ->call('saveAndMoveBookings')
        ->assertHasNoErrors();

    expect($booking->fresh()->booking_date->toDateString())->toBe('2026-10-15');
    Mail::assertNothingOutgoing();
});

test('leaves bookings that are past or refunded where they are', function (array $attributes) {
    Mail::fake();
    $class = createWednesdayClass();
    $booking = Booking::factory()->create(array_merge(['schedule_id' => $class->id], $attributes));
    $originalDate = $booking->booking_date->toDateString();

    Livewire::test('schedule.schedule-edit', ['schedule' => $class])
        ->set('day_of_week', DayOfWeek::Thursday->value)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.schedules.index'));

    expect($booking->fresh()->booking_date->toDateString())->toBe($originalDate);
    Mail::assertNothingOutgoing();
})->with([
    'a paid booking in the past' => [['payment_status' => PaymentStatus::Paid, 'booking_date' => '2026-09-30']],
    'a refunded booking next week' => [['payment_status' => PaymentStatus::Refunded, 'booking_date' => '2026-10-14']],
]);

test('saves an edit that moves nothing without confirmation or emails', function () {
    Mail::fake();
    $class = createWednesdayClass();
    Booking::factory()->paid()->create(['schedule_id' => $class->id, 'booking_date' => '2026-10-07']);

    Livewire::test('schedule.schedule-edit', ['schedule' => $class])
        ->set('max_capacity', '14')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.schedules.index'));

    expect($class->fresh()->max_capacity)->toBe(14);
    Mail::assertNothingOutgoing();
});

test('refuses to turn a class with upcoming bookings into a different kind of class', function (Closure $change, string $field) {
    Mail::fake();
    $class = createWednesdayClass();
    $booking = Booking::factory()->paid()->create(['schedule_id' => $class->id, 'booking_date' => '2026-10-07']);

    $change(Livewire::test('schedule.schedule-edit', ['schedule' => $class]), $class)
        ->call('saveAndMoveBookings')
        ->assertHasErrors($field)
        ->assertNoRedirect();

    expect($class->fresh())
        ->service_id->toBe($class->service_id)
        ->day_of_week->toBe(DayOfWeek::Wednesday);
    expect($booking->fresh()->booking_date->toDateString())->toBe('2026-10-07');
    Mail::assertNothingOutgoing();
})->with([
    'another service' => [
        fn ($form, Schedule $class) => $form->set('service_id', Service::factory()->create(['coach_id' => $class->service->coach_id])->id),
        'service_id',
    ],
    'a one-off class' => [
        fn ($form, Schedule $class) => $form->set('schedule_type', 'specific')->set('date', '2026-10-24'),
        'schedule_type',
    ],
]);
