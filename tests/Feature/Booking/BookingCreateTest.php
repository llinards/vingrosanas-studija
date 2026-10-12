<?php

use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Coach;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\UnavailableDate;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('booking create page can be rendered', function () {
    $this->get(route('admin.bookings.create'))
        ->assertSuccessful()
        ->assertSeeLivewire('booking.booking-create');
});

test('booking create page requires authentication', function () {
    auth()->logout();

    $this->get(route('admin.bookings.create'))
        ->assertRedirect(route('login'));
});

test('can create a booking with valid data', function () {
    $schedule = Schedule::factory()->create(['is_active' => true]);

    Livewire::test('booking.booking-create')
        ->set('service_type_id', $schedule->service->service_type_id)
        ->set('service_id', $schedule->service_id)
        ->set('schedule_id', $schedule->id)
        ->set('booking_date', $futureDate = now()->addDays(3)->toDateString())
        ->set('name', 'Jānis')
        ->set('surname', 'Bērziņš')
        ->set('phone', '+37120000000')
        ->set('email', 'janis@example.com')
        ->set('payment_status', 'paid')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.bookings.index'));

    $this->assertDatabaseHas('bookings', [
        'schedule_id' => $schedule->id,
        'name' => 'Jānis',
        'surname' => 'Bērziņš',
        'phone' => '+37120000000',
        'email' => 'janis@example.com',
        'payment_status' => PaymentStatus::Paid->value,
    ]);

    $booking = Booking::latest()->first();
    expect($booking->booking_date->format('Y-m-d'))->toBe($futureDate);
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

test('shows how many people are signed up and how many places are free for the chosen class and date', function () {
    $schedule = Schedule::factory()->create(['max_capacity' => 12]);
    Booking::factory()->paid()->withParticipants(2)->create(['schedule_id' => $schedule->id, 'booking_date' => '2026-12-02']);

    Livewire::test('booking.booking-create')
        ->set('service_type_id', $schedule->service->service_type_id)
        ->set('service_id', $schedule->service_id)
        ->set('schedule_id', $schedule->id)
        ->set('booking_date', '2026-12-02')
        ->assertSee('Pieteikušies: 2 no 12 · Brīvas vietas: 10')
        ->assertDontSee('Atlikušās vietas');
});

test('refuses a new booking for a date that has passed', function () {
    $this->travelTo('2026-10-10 12:00:00');
    $schedule = Schedule::factory()->create();

    Livewire::test('booking.booking-create')
        ->set('service_type_id', $schedule->service->service_type_id)
        ->set('service_id', $schedule->service_id)
        ->set('schedule_id', $schedule->id)
        ->set('booking_date', '2026-10-09')
        ->set('name', 'Jānis')
        ->set('surname', 'Bērziņš')
        ->set('phone', '+37120000000')
        ->set('email', 'janis@example.com')
        ->set('payment_status', 'paid')
        ->call('save')
        ->assertHasErrors(['booking_date' => 'after_or_equal']);

    expect(Booking::count())->toBe(0);
});

/**
 * Open the admin create form with the given class and date chosen. "Now" is Monday 5 October 2026.
 */
function adminBookingFormFor(Schedule $schedule, string $date): Testable
{
    test()->travelTo(Carbon::parse('2026-10-05 09:00:00'));

    return Livewire::test('booking.booking-create')
        ->set('service_type_id', $schedule->service->service_type_id)
        ->set('service_id', $schedule->service_id)
        ->set('schedule_id', $schedule->id)
        ->set('booking_date', $date);
}

/**
 * A weekly class on Wednesdays; 7 October 2026 is a Wednesday.
 *
 * @param  array<string, mixed>  $attributes
 */
function wednesdayClassForAdmin(array $attributes = []): Schedule
{
    return Schedule::factory()->create(array_merge([
        'day_of_week' => 3,
        'date' => null,
        'start_time' => '18:00',
        'max_capacity' => 3,
    ], $attributes));
}

test('shows no warning for a booking that fits a class on a day it runs', function () {
    $class = wednesdayClassForAdmin();

    adminBookingFormFor($class, '2026-10-07')
        ->assertDontSee('Nodarbība šajā datumā ir pilna.')
        ->assertDontSee('Šī nodarbība šajā nedēļas dienā nenotiek.')
        ->assertDontSee('Šajā datumā studija ir slēgta vai treneris nav pieejams.');
});

test('warns that the class is full but still saves the booking', function () {
    $class = wednesdayClassForAdmin(['max_capacity' => 1]);
    Booking::factory()->paid()->create(['schedule_id' => $class->id, 'booking_date' => '2026-10-07']);

    adminBookingFormFor($class, '2026-10-07')
        ->assertSee('Nodarbība šajā datumā ir pilna.')
        ->set('name', 'Jānis')
        ->set('surname', 'Bērziņš')
        ->set('phone', '+37120000000')
        ->set('email', 'janis@example.com')
        ->set('payment_status', 'paid')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.bookings.index'));

    expect(Booking::where('schedule_id', $class->id)->count())->toBe(2);
});

test('warns that the class is full only when the entered participants no longer fit', function (int $participants, bool $warningExpected) {
    $class = wednesdayClassForAdmin(['max_capacity' => 3]);
    Booking::factory()->paid()->create(['schedule_id' => $class->id, 'booking_date' => '2026-10-07']);

    $form = adminBookingFormFor($class, '2026-10-07')->set('participant_count', $participants);

    $warningExpected
        ? $form->assertSee('Nodarbība šajā datumā ir pilna.')
        : $form->assertDontSee('Nodarbība šajā datumā ir pilna.');
})->with([
    'two more people fit' => [2, false],
    'three more people do not' => [3, true],
]);

test('warns that an individual session already has a booking', function () {
    $class = wednesdayClassForAdmin(['service_id' => Service::factory()->exclusive()->create()->id, 'max_capacity' => 3]);
    Booking::factory()->paid()->create(['schedule_id' => $class->id, 'booking_date' => '2026-10-07']);

    adminBookingFormFor($class, '2026-10-07')
        ->assertSee('Nodarbība šajā datumā ir pilna.');
});

test('warns when the date is not a day the class runs', function (array $schedule, string $date) {
    $class = wednesdayClassForAdmin($schedule);

    adminBookingFormFor($class, $date)
        ->assertSee('Šī nodarbība šajā nedēļas dienā nenotiek.');
})->with([
    'a Tuesday for a Wednesday class' => [[], '2026-10-06'],
    'another date for a one-off class' => [['day_of_week' => null, 'date' => '2026-10-24'], '2026-10-25'],
]);

test('warns when the studio is closed on that date', function () {
    $class = wednesdayClassForAdmin();
    UnavailableDate::factory()->range(Carbon::parse('2026-10-07'), Carbon::parse('2026-10-07'))->create();

    adminBookingFormFor($class, '2026-10-07')
        ->assertSee('Šajā datumā studija ir slēgta vai treneris nav pieejams.');
});

test('does not warn about the absence of another coach', function () {
    $class = wednesdayClassForAdmin();
    UnavailableDate::factory()->forCoach(Coach::factory()->create())->range(Carbon::parse('2026-10-07'), Carbon::parse('2026-10-07'))->create();

    adminBookingFormFor($class, '2026-10-07')
        ->assertDontSee('Šajā datumā studija ir slēgta vai treneris nav pieejams.');
});
