<?php

use App\Enums\AttendanceStatus;
use App\Models\Booking;
use App\Models\Coach;
use App\Models\Membership;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\ServiceType;
use App\Models\UnavailableDate;
use Illuminate\Support\Carbon;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * A paid membership whose manage page is opened with the access code "cs_test_manage_own".
 * "Now" in these tests is Monday 5 October 2026, so the period runs until 5 November 2026.
 */
function membershipWithManageAccess(): Membership
{
    test()->travelTo(Carbon::parse('2026-10-05 09:00:00'));

    return Membership::factory()->paid()->create([
        'stripe_checkout_session_id' => 'cs_test_manage_own',
        'period_start' => today(),
        'period_end' => today()->addMonth(),
    ]);
}

/**
 * A membership-eligible class at 18:00, on Wednesdays unless another day is given
 * (Wednesday 2026-10-07 in the pinned week).
 *
 * @param  array<string, mixed>  $serviceAttributes
 * @param  array<string, mixed>  $scheduleAttributes
 */
function membershipClassOnWednesday(array $serviceAttributes = [], array $scheduleAttributes = []): Schedule
{
    $service = Service::factory()->membershipEligible()->create([
        'service_type_id' => ServiceType::factory(),
        'is_active' => true,
        ...$serviceAttributes,
    ]);

    return Schedule::factory()->create([
        'service_id' => $service->id,
        'day_of_week' => 3,
        'start_time' => '18:00',
        'max_capacity' => 10,
        'is_active' => true,
        ...$scheduleAttributes,
    ]);
}

/**
 * Open the manage page and start moving the given session.
 */
function startMovingSession(Membership $membership, Booking $booking): Testable
{
    return Livewire::withQueryParams(['session_id' => 'cs_test_manage_own'])
        ->test('membership.membership-manage', ['membership' => $membership])
        ->call('startRebook', $booking->id);
}

test('a session is moved to the chosen time once the customer confirms', function () {
    $membership = membershipWithManageAccess();
    $class = membershipClassOnWednesday();
    $membership->service->update(['service_type_id' => $class->service->service_type_id]);
    $booking = Booking::factory()->forMembership($membership)->create(['booking_date' => '2026-10-09']);

    startMovingSession($membership, $booking)
        ->call('chooseTarget', $class->id, '2026-10-07')
        ->assertSet('targetScheduleId', $class->id)
        ->assertSee('Pārcelt nodarbību?')
        ->call('confirmRebook')
        ->assertHasNoErrors()
        ->assertSet('rebookingBookingId', null)
        ->assertSee('Nodarbība pārcelta.');

    expect($booking->refresh())
        ->schedule_id->toBe($class->id)
        ->booking_date->toDateString()->toBe('2026-10-07');
});

test('moving a session shows the week of that session with the classes of the membership category', function () {
    $membership = membershipWithManageAccess();
    $wednesdayClass = membershipClassOnWednesday(['name' => 'Fusion Pilates']);
    $categoryId = $wednesdayClass->service->service_type_id;
    $membership->service->update(['service_type_id' => $categoryId]);
    membershipClassOnWednesday(['name' => 'VERVE', 'service_type_id' => $categoryId], ['day_of_week' => 5]);
    membershipClassOnWednesday(['name' => 'Fizioterapija bērniem']);
    $booking = Booking::factory()->forMembership($membership)->create([
        'schedule_id' => $wednesdayClass->id,
        'booking_date' => '2026-10-14',
    ]);

    startMovingSession($membership, $booking)
        ->assertSet('week', 1)
        ->assertSee('Pārcelt nodarbību')
        ->assertSee('Trešdiena, 14.10. 18:00 Fusion Pilates')
        ->assertSee("day: '2026-10-14'", escape: false)
        ->assertSee('Pašreizējā')
        ->assertSee('VERVE')
        ->assertSee('Pārcelt šeit')
        ->assertDontSee('Fizioterapija bērniem');
});

test('the week arrows stay within the membership period', function () {
    $membership = membershipWithManageAccess();
    $booking = Booking::factory()->forMembership($membership)->create(['booking_date' => '2026-10-07']);

    startMovingSession($membership, $booking)
        ->assertSet('week', 0)
        ->call('previousWeek')
        ->assertSet('week', 0)
        ->call('nextWeek')->call('nextWeek')->call('nextWeek')->call('nextWeek')->call('nextWeek')
        ->assertSet('week', 4);
});

test('times the session cannot move to are shown without a button', function (Closure $arrange, int $week, string $label) {
    $membership = membershipWithManageAccess();
    $class = membershipClassOnWednesday(scheduleAttributes: ['day_of_week' => 5]);
    $membership->service->update(['service_type_id' => $class->service->service_type_id]);
    $booking = Booking::factory()->forMembership($membership)->create(['booking_date' => '2026-10-07']);
    $arrange($membership, $class);

    startMovingSession($membership, $booking)
        ->set('week', $week)
        ->assertSee($label)
        ->assertDontSee('Pārcelt šeit');
})->with([
    'after the membership period' => [fn () => null, 4, 'Ārpus perioda'],
    'a full class' => [function (Membership $membership, Schedule $class) {
        $class->update(['max_capacity' => 1]);
        Booking::factory()->paid()->create(['schedule_id' => $class->id, 'booking_date' => '2026-10-09']);
    }, 0, 'Pilns'],
    'a time this membership already has' => [function (Membership $membership, Schedule $class) {
        Booking::factory()->forMembership($membership)->create(['schedule_id' => $class->id, 'booking_date' => '2026-10-09']);
    }, 0, 'Jau rezervēta'],
]);

test('a booking outside this membership cannot be opened for rescheduling', function (Closure $createBooking) {
    $membership = membershipWithManageAccess();
    $booking = $createBooking();

    startMovingSession($membership, $booking)->assertNotFound();
})->with([
    'a session of another membership' => fn () => Booking::factory()
        ->forMembership(Membership::factory()->paid()->create())
        ->create(),
    'a single booking' => fn () => Booking::factory()->paid()->create(),
]);

test('a session of this membership that cannot be moved any more is refused', function (Closure $createBooking) {
    $membership = membershipWithManageAccess();
    $booking = $createBooking($membership);

    startMovingSession($membership, $booking)->assertNotFound();
})->with([
    'past' => fn (Membership $membership) => Booking::factory()->forMembership($membership)->create(['booking_date' => '2026-10-02']),
    'attended' => fn (Membership $membership) => Booking::factory()->forMembership($membership)->attended()->create(),
    'refunded' => fn (Membership $membership) => Booking::factory()->forMembership($membership)->refunded()->create(),
]);

test('the session being rescheduled cannot be swapped from the browser', function () {
    $membership = membershipWithManageAccess();
    $ownBooking = Booking::factory()->forMembership($membership)->create();
    $otherBooking = Booking::factory()->paid()->create();

    startMovingSession($membership, $ownBooking)
        ->set('rebookingBookingId', $otherBooking->id);
})->throws(CannotUpdateLockedPropertyException::class);

test('the chosen new time cannot be changed from the browser', function () {
    $membership = membershipWithManageAccess();
    $booking = Booking::factory()->forMembership($membership)->create();

    startMovingSession($membership, $booking)
        ->set('targetScheduleId', membershipClassOnWednesday()->id);
})->throws(CannotUpdateLockedPropertyException::class);

test('a session that was attended after the move started is not moved', function () {
    $membership = membershipWithManageAccess();
    $class = membershipClassOnWednesday();
    $membership->service->update(['service_type_id' => $class->service->service_type_id]);
    $booking = Booking::factory()->forMembership($membership)->create();
    $originalScheduleId = $booking->schedule_id;

    $component = startMovingSession($membership, $booking)
        ->call('chooseTarget', $class->id, '2026-10-07');

    $booking->update(['attendance_status' => AttendanceStatus::Attended]);

    $component->call('confirmRebook')->assertNotFound();

    expect($booking->refresh()->schedule_id)->toBe($originalScheduleId);
});

test('a session is not moved to a time the rules do not allow', function (Closure $createClass, string $date, string $message) {
    $membership = membershipWithManageAccess();
    $booking = Booking::factory()->forMembership($membership)->create(['booking_date' => '2026-10-09']);
    $originalScheduleId = $booking->schedule_id;
    $class = $createClass($membership);
    $membership->service->update(['service_type_id' => $class->service->service_type_id]);

    startMovingSession($membership, $booking)
        ->call('chooseTarget', $class->id, $date)
        ->call('confirmRebook')
        ->assertHasErrors('rebook')
        ->assertSee($message)
        ->assertSet('rebookingBookingId', $booking->id);

    expect($booking->refresh())
        ->schedule_id->toBe($originalScheduleId)
        ->booking_date->toDateString()->toBe('2026-10-09');
})->with([
    'a past date' => [fn () => membershipClassOnWednesday(), '2026-09-30', 'Datumam jābūt abonementa periodā.'],
    'a date after the membership period' => [fn () => membershipClassOnWednesday(), '2026-11-11', 'Datumam jābūt abonementa periodā.'],
    'a day the class does not run' => [fn () => membershipClassOnWednesday(), '2026-10-08', 'Šis laiks vairs nav pieejams.'],
    'a day the studio is closed' => [function () {
        UnavailableDate::factory()->range(Carbon::parse('2026-10-07'), Carbon::parse('2026-10-07'))->create();

        return membershipClassOnWednesday();
    }, '2026-10-07', 'Šis laiks vairs nav pieejams.'],
    'a class not allowed for memberships' => [fn () => membershipClassOnWednesday(['is_membership_eligible' => false]), '2026-10-07', 'Šis laiks vairs nav pieejams.'],
    'a class of an inactive coach' => [fn () => membershipClassOnWednesday(['coach_id' => Coach::factory()->create(['is_active' => false])->id]), '2026-10-07', 'Šis laiks vairs nav pieejams.'],
    'a full class' => [function () {
        $class = membershipClassOnWednesday(scheduleAttributes: ['max_capacity' => 1]);
        Booking::factory()->paid()->create(['schedule_id' => $class->id, 'booking_date' => '2026-10-07']);

        return $class;
    }, '2026-10-07', 'Šis laiks vairs nav pieejams.'],
    'a taken individual session' => [function () {
        $class = membershipClassOnWednesday(['is_exclusive' => true]);
        Booking::factory()->paid()->create(['schedule_id' => $class->id, 'booking_date' => '2026-10-07']);

        return $class;
    }, '2026-10-07', 'Šis laiks vairs nav pieejams.'],
    'a time this membership already has' => [function (Membership $membership) {
        $class = membershipClassOnWednesday();
        Booking::factory()->forMembership($membership)->create(['schedule_id' => $class->id, 'booking_date' => '2026-10-07']);

        return $class;
    }, '2026-10-07', 'Šajā laikā jums jau ir rezervēta nodarbība.'],
]);

test('a session is not moved to a class of another category than the membership', function () {
    $membership = membershipWithManageAccess();
    $booking = Booking::factory()->forMembership($membership)->create(['booking_date' => '2026-10-09']);
    $originalScheduleId = $booking->schedule_id;
    $otherCategoryClass = membershipClassOnWednesday(['name' => 'Fizioterapija bērniem']);

    startMovingSession($membership, $booking)
        ->assertDontSee('Fizioterapija bērniem')
        ->call('chooseTarget', $otherCategoryClass->id, '2026-10-07')
        ->call('confirmRebook')
        ->assertHasErrors('rebook');

    expect($booking->refresh())
        ->schedule_id->toBe($originalScheduleId)
        ->booking_date->toDateString()->toBe('2026-10-09');
});

test('classes of an inactive coach are not offered for rescheduling', function () {
    $membership = membershipWithManageAccess();
    $class = membershipClassOnWednesday([
        'name' => 'Pilates ar neaktīvu treneri',
        'coach_id' => Coach::factory()->create(['is_active' => false])->id,
    ]);
    $membership->service->update(['service_type_id' => $class->service->service_type_id]);
    $booking = Booking::factory()->forMembership($membership)->create(['booking_date' => '2026-10-09']);

    startMovingSession($membership, $booking)
        ->assertSee('Pārcelt nodarbību')
        ->assertDontSee('Pilates ar neaktīvu treneri');
});

test('cancelling the move goes back to the list without changing the session', function () {
    $membership = membershipWithManageAccess();
    $booking = Booking::factory()->forMembership($membership)->create(['booking_date' => '2026-10-09']);

    startMovingSession($membership, $booking)
        ->call('cancelRebook')
        ->assertSet('rebookingBookingId', null)
        ->assertSee('Rezervētās nodarbības');

    expect($booking->refresh()->booking_date->toDateString())->toBe('2026-10-09');
});
