<?php

use App\Models\Booking;
use App\Models\Membership;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\ServiceType;
use Illuminate\Support\Carbon;
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
