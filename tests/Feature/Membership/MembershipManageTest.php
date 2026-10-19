<?php

use App\Enums\AttendanceStatus;
use App\Models\Booking;
use App\Models\Membership;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\ServiceType;
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
