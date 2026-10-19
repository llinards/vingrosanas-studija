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
use Livewire\Livewire;

test('session is moved to a time slot offered for the selected date', function () {
    Carbon::setTestNow(Carbon::today()->setTime(8, 0));

    $newDate = today()->addDay();

    $membership = Membership::factory()->paid()->create([
        'stripe_checkout_session_id' => 'cs_test_manage_offered',
        'period_start' => today(),
        'period_end' => today()->addMonth(),
    ]);
    $serviceType = ServiceType::factory()->create();
    $eligibleService = Service::factory()->membershipEligible()->create([
        'service_type_id' => $serviceType->id,
        'is_active' => true,
    ]);
    $offeredSchedule = Schedule::factory()->create([
        'service_id' => $eligibleService->id,
        'day_of_week' => $newDate->dayOfWeekIso,
        'start_time' => '18:00',
        'is_active' => true,
    ]);
    $booking = Booking::factory()->forMembership($membership)->create();

    Livewire::withQueryParams(['session_id' => 'cs_test_manage_offered'])
        ->test('membership.membership-manage', ['membership' => $membership])
        ->call('startRebook', $booking->id)
        ->set('rebook_service_type_id', $serviceType->id)
        ->set('rebook_service_id', $eligibleService->id)
        ->set('rebook_date', $newDate->toDateString())
        ->set('rebook_schedule_id', $offeredSchedule->id)
        ->call('confirmRebook')
        ->assertHasNoErrors();

    $booking->refresh();

    expect($booking->schedule_id)->toBe($offeredSchedule->id)
        ->and($booking->booking_date->toDateString())->toBe($newDate->toDateString());

    Carbon::setTestNow();
});

test('session is not moved to a time slot that does not run on the selected date', function () {
    Carbon::setTestNow(Carbon::today()->setTime(8, 0));

    $selectedDate = today()->addDay();
    $otherDate = today()->addDays(2);

    $membership = Membership::factory()->paid()->create([
        'stripe_checkout_session_id' => 'cs_test_manage_not_offered',
        'period_start' => today(),
        'period_end' => today()->addMonth(),
    ]);
    $serviceType = ServiceType::factory()->create();
    $eligibleService = Service::factory()->membershipEligible()->create([
        'service_type_id' => $serviceType->id,
        'is_active' => true,
    ]);
    $otherDaySchedule = Schedule::factory()->create([
        'service_id' => $eligibleService->id,
        'day_of_week' => $otherDate->dayOfWeekIso,
        'start_time' => '15:00',
        'is_active' => true,
    ]);
    $booking = Booking::factory()->forMembership($membership)->create();
    $originalScheduleId = $booking->schedule_id;

    Livewire::withQueryParams(['session_id' => 'cs_test_manage_not_offered'])
        ->test('membership.membership-manage', ['membership' => $membership])
        ->call('startRebook', $booking->id)
        ->set('rebook_service_type_id', $serviceType->id)
        ->set('rebook_service_id', $eligibleService->id)
        ->set('rebook_date', $selectedDate->toDateString())
        ->set('rebook_schedule_id', $otherDaySchedule->id)
        ->call('confirmRebook')
        ->assertSet('rebookingBookingId', $booking->id);

    expect($booking->refresh()->schedule_id)->toBe($originalScheduleId);

    Carbon::setTestNow();
});

/**
 * A paid membership whose manage page is opened with the access code "cs_test_manage_own".
 */
function membershipWithManageAccess(): Membership
{
    return Membership::factory()->paid()->create([
        'stripe_checkout_session_id' => 'cs_test_manage_own',
        'period_start' => today(),
        'period_end' => today()->addMonth(),
    ]);
}

test('a booking outside this membership cannot be opened for rescheduling', function (Closure $createBooking) {
    $this->travelTo(Carbon::parse('2026-10-05 09:00:00'));

    $membership = membershipWithManageAccess();
    $booking = $createBooking();

    Livewire::withQueryParams(['session_id' => 'cs_test_manage_own'])
        ->test('membership.membership-manage', ['membership' => $membership])
        ->call('startRebook', $booking->id)
        ->assertNotFound();
})->with([
    'a session of another membership' => fn () => Booking::factory()
        ->forMembership(Membership::factory()->paid()->create())
        ->create(),
    'a single booking' => fn () => Booking::factory()->paid()->create(),
]);

test('a session of this membership that cannot be moved any more is refused', function (Closure $createBooking) {
    $this->travelTo(Carbon::parse('2026-10-05 09:00:00'));

    $membership = membershipWithManageAccess();
    $booking = $createBooking($membership);

    Livewire::withQueryParams(['session_id' => 'cs_test_manage_own'])
        ->test('membership.membership-manage', ['membership' => $membership])
        ->call('startRebook', $booking->id)
        ->assertNotFound();
})->with([
    'past' => fn (Membership $membership) => Booking::factory()->forMembership($membership)->create(['booking_date' => '2026-10-02']),
    'attended' => fn (Membership $membership) => Booking::factory()->forMembership($membership)->attended()->create(),
    'refunded' => fn (Membership $membership) => Booking::factory()->forMembership($membership)->refunded()->create(),
]);

test('the session being rescheduled cannot be swapped from the browser', function () {
    $this->travelTo(Carbon::parse('2026-10-05 09:00:00'));

    $membership = membershipWithManageAccess();
    $ownBooking = Booking::factory()->forMembership($membership)->create();
    $otherBooking = Booking::factory()->paid()->create();

    Livewire::withQueryParams(['session_id' => 'cs_test_manage_own'])
        ->test('membership.membership-manage', ['membership' => $membership])
        ->call('startRebook', $ownBooking->id)
        ->set('rebookingBookingId', $otherBooking->id);
})->throws(CannotUpdateLockedPropertyException::class);

test('a session that was attended after the reschedule form opened is not moved', function () {
    $this->travelTo(Carbon::parse('2026-10-05 09:00:00'));

    $membership = membershipWithManageAccess();
    $serviceType = ServiceType::factory()->create();
    $eligibleService = Service::factory()->membershipEligible()->create([
        'service_type_id' => $serviceType->id,
        'is_active' => true,
    ]);
    $offeredSchedule = Schedule::factory()->create([
        'service_id' => $eligibleService->id,
        'day_of_week' => 3,
        'start_time' => '18:00',
        'is_active' => true,
    ]);
    $booking = Booking::factory()->forMembership($membership)->create();
    $originalScheduleId = $booking->schedule_id;

    $component = Livewire::withQueryParams(['session_id' => 'cs_test_manage_own'])
        ->test('membership.membership-manage', ['membership' => $membership])
        ->call('startRebook', $booking->id)
        ->set('rebook_service_type_id', $serviceType->id)
        ->set('rebook_service_id', $eligibleService->id)
        ->set('rebook_date', '2026-10-07')
        ->set('rebook_schedule_id', $offeredSchedule->id);

    $booking->update(['attendance_status' => AttendanceStatus::Attended]);

    $component->call('confirmRebook')->assertNotFound();

    expect($booking->refresh()->schedule_id)->toBe($originalScheduleId);
});

/**
 * A membership-eligible Wednesday 18:00 class (Wednesday 2026-10-07 in the pinned week).
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

test('a session is not moved to a time the rules do not allow', function (Closure $createClass, string $date, string $errorKey, string $message) {
    $this->travelTo(Carbon::parse('2026-10-05 09:00:00'));

    $membership = membershipWithManageAccess();
    $booking = Booking::factory()->forMembership($membership)->create(['booking_date' => '2026-10-09']);
    $originalScheduleId = $booking->schedule_id;
    $class = $createClass();

    Livewire::withQueryParams(['session_id' => 'cs_test_manage_own'])
        ->test('membership.membership-manage', ['membership' => $membership])
        ->call('startRebook', $booking->id)
        ->set('rebook_service_type_id', $class->service->service_type_id)
        ->set('rebook_service_id', $class->service_id)
        ->set('rebook_date', $date)
        ->set('rebook_schedule_id', $class->id)
        ->call('confirmRebook')
        ->assertHasErrors($errorKey)
        ->assertSee($message);

    expect($booking->refresh())
        ->schedule_id->toBe($originalScheduleId)
        ->booking_date->toDateString()->toBe('2026-10-09');
})->with([
    'a past date' => [fn () => membershipClassOnWednesday(), '2026-09-30', 'rebook_date', 'Datumam jābūt abonementa periodā.'],
    'a date after the membership period' => [fn () => membershipClassOnWednesday(), '2026-11-11', 'rebook_date', 'Datumam jābūt abonementa periodā.'],
    'a day the studio is closed' => [function () {
        UnavailableDate::factory()->range(Carbon::parse('2026-10-07'), Carbon::parse('2026-10-07'))->create();

        return membershipClassOnWednesday();
    }, '2026-10-07', 'rebook', 'Šis laiks vairs nav pieejams.'],
    'a class not allowed for memberships' => [fn () => membershipClassOnWednesday(['is_membership_eligible' => false]), '2026-10-07', 'rebook', 'Šis laiks vairs nav pieejams.'],
    'a class of an inactive coach' => [fn () => membershipClassOnWednesday(['coach_id' => Coach::factory()->create(['is_active' => false])->id]), '2026-10-07', 'rebook', 'Šis laiks vairs nav pieejams.'],
    'a full class' => [function () {
        $class = membershipClassOnWednesday(scheduleAttributes: ['max_capacity' => 1]);
        Booking::factory()->paid()->create(['schedule_id' => $class->id, 'booking_date' => '2026-10-07']);

        return $class;
    }, '2026-10-07', 'rebook', 'Šis laiks vairs nav pieejams.'],
    'a taken individual session' => [function () {
        $class = membershipClassOnWednesday(['is_exclusive' => true]);
        Booking::factory()->paid()->create(['schedule_id' => $class->id, 'booking_date' => '2026-10-07']);

        return $class;
    }, '2026-10-07', 'rebook', 'Šis laiks vairs nav pieejams.'],
]);

test('classes of an inactive coach are not offered for rescheduling', function () {
    $this->travelTo(Carbon::parse('2026-10-05 09:00:00'));

    $membership = membershipWithManageAccess();
    $booking = Booking::factory()->forMembership($membership)->create();
    $class = membershipClassOnWednesday([
        'name' => 'Pilates ar neaktīvu treneri',
        'coach_id' => Coach::factory()->create(['is_active' => false])->id,
    ]);

    Livewire::withQueryParams(['session_id' => 'cs_test_manage_own'])
        ->test('membership.membership-manage', ['membership' => $membership])
        ->call('startRebook', $booking->id)
        ->set('rebook_service_type_id', $class->service->service_type_id)
        ->assertDontSee('Pilates ar neaktīvu treneri');
});
