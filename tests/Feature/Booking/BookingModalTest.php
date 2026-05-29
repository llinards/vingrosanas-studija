<?php

use App\Actions\CreateMembershipCheckoutSession;
use App\Actions\CreateStripeCheckoutSession;
use App\Models\Booking;
use App\Models\Membership;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\ServiceType;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Stripe\Exception\UnknownApiErrorException;

test('booking modal can be rendered on welcome page', function () {
    $this->get(route('home'))
        ->assertSuccessful()
        ->assertSeeLivewire('booking-modal');
});

test('step 1 validates service type and service selection', function () {
    Livewire::test('booking-modal')
        ->call('nextStep')
        ->assertHasErrors(['service_type_id', 'service_id']);
});

test('step 1 shows services filtered by service type', function () {
    $serviceType = ServiceType::factory()->create();
    $service = Service::factory()->create([
        'service_type_id' => $serviceType->id,
        'is_active' => true,
    ]);
    Schedule::factory()->create([
        'service_id' => $service->id,
        'is_active' => true,
    ]);

    Livewire::test('booking-modal')
        ->set('service_type_id', $serviceType->id)
        ->assertSee($service->name);
});

test('step 2 validates date and time slot selection', function () {
    $serviceType = ServiceType::factory()->create();
    $service = Service::factory()->create([
        'service_type_id' => $serviceType->id,
        'is_active' => true,
    ]);
    Schedule::factory()->create([
        'service_id' => $service->id,
        'is_active' => true,
    ]);

    Livewire::test('booking-modal')
        ->set('service_type_id', $serviceType->id)
        ->set('service_id', $service->id)
        ->set('step', 2)
        ->call('nextStep')
        ->assertHasErrors(['selectedDate', 'schedule_id']);
});

test('step 3 validates customer info', function () {
    Livewire::test('booking-modal')
        ->set('step', 3)
        ->call('nextStep')
        ->assertHasErrors(['name', 'surname', 'phone', 'email']);
});

test('step 3 validates email format', function () {
    Livewire::test('booking-modal')
        ->set('step', 3)
        ->set('name', 'Jānis')
        ->set('surname', 'Bērziņš')
        ->set('phone', '+37120000000')
        ->set('email', 'not-an-email')
        ->call('nextStep')
        ->assertHasErrors(['email' => 'email']);
});

test('can navigate between steps', function () {
    $serviceType = ServiceType::factory()->create();
    $service = Service::factory()->create([
        'service_type_id' => $serviceType->id,
        'is_active' => true,
    ]);
    Schedule::factory()->create([
        'service_id' => $service->id,
        'is_active' => true,
    ]);

    Livewire::test('booking-modal')
        ->set('service_type_id', $serviceType->id)
        ->set('service_id', $service->id)
        ->call('nextStep')
        ->assertSet('step', 2)
        ->call('previousStep')
        ->assertSet('step', 1);
});

test('resetting service type clears service selection', function () {
    $serviceType = ServiceType::factory()->create();
    $service = Service::factory()->create([
        'service_type_id' => $serviceType->id,
        'is_active' => true,
    ]);

    Livewire::test('booking-modal')
        ->set('service_type_id', $serviceType->id)
        ->set('service_id', $service->id)
        ->set('service_type_id', null)
        ->assertSet('service_id', null);
});

test('validation messages are in latvian', function () {
    Livewire::test('booking-modal')
        ->set('step', 3)
        ->call('nextStep')
        ->assertSee('Vārds ir obligāts.');
});

test('past time slots are not shown for today', function () {
    Carbon::setTestNow(Carbon::today()->setTime(10, 0));

    $serviceType = ServiceType::factory()->create();
    $service = Service::factory()->create([
        'service_type_id' => $serviceType->id,
        'is_active' => true,
    ]);

    $todayDayOfWeek = today()->dayOfWeekIso;

    $pastSchedule = Schedule::factory()->create([
        'service_id' => $service->id,
        'day_of_week' => $todayDayOfWeek,
        'start_time' => '09:00',
        'is_active' => true,
    ]);

    $futureSchedule = Schedule::factory()->create([
        'service_id' => $service->id,
        'day_of_week' => $todayDayOfWeek,
        'start_time' => '11:00',
        'is_active' => true,
    ]);

    $component = Livewire::test('booking-modal')
        ->set('service_type_id', $serviceType->id)
        ->set('service_id', $service->id)
        ->set('selectedDate', today()->toDateString());

    $slots = $component->instance()->availableTimeSlots;
    $slotScheduleIds = collect($slots)->pluck('schedule_id')->toArray();

    expect($slotScheduleIds)->not->toContain($pastSchedule->id);
    expect($slotScheduleIds)->toContain($futureSchedule->id);

    Carbon::setTestNow();
});

test('future time slots are shown for tomorrow', function () {
    Carbon::setTestNow(Carbon::today()->setTime(10, 0));

    $serviceType = ServiceType::factory()->create();
    $service = Service::factory()->create([
        'service_type_id' => $serviceType->id,
        'is_active' => true,
    ]);

    $tomorrowDayOfWeek = today()->addDay()->dayOfWeekIso;

    $morningSchedule = Schedule::factory()->create([
        'service_id' => $service->id,
        'day_of_week' => $tomorrowDayOfWeek,
        'start_time' => '09:00',
        'is_active' => true,
    ]);

    $component = Livewire::test('booking-modal')
        ->set('service_type_id', $serviceType->id)
        ->set('service_id', $service->id)
        ->set('selectedDate', today()->addDay()->toDateString());

    $slots = $component->instance()->availableTimeSlots;
    $slotScheduleIds = collect($slots)->pluck('schedule_id')->toArray();

    expect($slotScheduleIds)->toContain($morningSchedule->id);

    Carbon::setTestNow();
});

test('today is marked unavailable when all time slots have passed', function () {
    Carbon::setTestNow(Carbon::today()->setTime(20, 0));

    $serviceType = ServiceType::factory()->create();
    $service = Service::factory()->create([
        'service_type_id' => $serviceType->id,
        'is_active' => true,
    ]);

    $todayDayOfWeek = today()->dayOfWeekIso;

    Schedule::factory()->create([
        'service_id' => $service->id,
        'day_of_week' => $todayDayOfWeek,
        'start_time' => '09:00',
        'is_active' => true,
    ]);

    Schedule::factory()->create([
        'service_id' => $service->id,
        'day_of_week' => $todayDayOfWeek,
        'start_time' => '14:00',
        'is_active' => true,
    ]);

    $component = Livewire::test('booking-modal')
        ->set('service_type_id', $serviceType->id)
        ->set('service_id', $service->id);

    $unavailableDates = $component->instance()->unavailableDates;

    expect($unavailableDates)->toContain(today()->toDateString());

    Carbon::setTestNow();
});

test('regular service shows remaining capacity on time slots', function () {
    Carbon::setTestNow(Carbon::today()->setTime(8, 0));

    $serviceType = ServiceType::factory()->create();
    $service = Service::factory()->create([
        'service_type_id' => $serviceType->id,
        'is_active' => true,
        'is_exclusive' => false,
    ]);

    $todayDayOfWeek = today()->dayOfWeekIso;

    Schedule::factory()->create([
        'service_id' => $service->id,
        'day_of_week' => $todayDayOfWeek,
        'start_time' => '10:00',
        'is_active' => true,
    ]);

    Livewire::test('booking-modal')
        ->set('service_type_id', $serviceType->id)
        ->set('service_id', $service->id)
        ->set('step', 2)
        ->set('selectedDate', today()->toDateString())
        ->assertSee(__('vietas'));

    Carbon::setTestNow();
});

test('exclusive service hides remaining capacity on time slots', function () {
    Carbon::setTestNow(Carbon::today()->setTime(8, 0));

    $serviceType = ServiceType::factory()->create();
    $service = Service::factory()->exclusive()->create([
        'service_type_id' => $serviceType->id,
        'is_active' => true,
    ]);

    $todayDayOfWeek = today()->dayOfWeekIso;

    Schedule::factory()->create([
        'service_id' => $service->id,
        'day_of_week' => $todayDayOfWeek,
        'start_time' => '10:00',
        'is_active' => true,
    ]);

    Livewire::test('booking-modal')
        ->set('service_type_id', $serviceType->id)
        ->set('service_id', $service->id)
        ->set('step', 2)
        ->set('selectedDate', today()->toDateString())
        ->assertDontSee(__('vietas'))
        ->assertDontSee(__('vieta'));

    Carbon::setTestNow();
});

test('step 2 rejects past booking date', function () {
    $serviceType = ServiceType::factory()->create();
    $service = Service::factory()->create([
        'service_type_id' => $serviceType->id,
        'is_active' => true,
    ]);
    $schedule = Schedule::factory()->create([
        'service_id' => $service->id,
        'is_active' => true,
    ]);

    Livewire::test('booking-modal')
        ->set('service_type_id', $serviceType->id)
        ->set('service_id', $service->id)
        ->set('step', 2)
        ->set('selectedDate', now()->subDay()->toDateString())
        ->set('schedule_id', $schedule->id)
        ->call('nextStep')
        ->assertHasErrors(['selectedDate' => 'after_or_equal']);
});

// ========================================
// UX IMPROVEMENTS
// ========================================

test('schedule_id error is visible when slot becomes unavailable', function () {
    Carbon::setTestNow(Carbon::today()->setTime(8, 0));

    $serviceType = ServiceType::factory()->create();
    $service = Service::factory()->exclusive()->create([
        'service_type_id' => $serviceType->id,
        'is_active' => true,
    ]);

    $todayDayOfWeek = today()->dayOfWeekIso;
    $schedule = Schedule::factory()->create([
        'service_id' => $service->id,
        'day_of_week' => $todayDayOfWeek,
        'start_time' => '10:00',
        'is_active' => true,
    ]);

    // Create an existing booking to fill the exclusive slot
    Booking::factory()->create([
        'schedule_id' => $schedule->id,
        'booking_date' => today(),
        'participant_count' => 1,
    ]);

    Livewire::test('booking-modal')
        ->set('service_type_id', $serviceType->id)
        ->set('service_id', $service->id)
        ->set('selectedDate', today()->toDateString())
        ->set('schedule_id', $schedule->id)
        ->set('step', 4)
        ->set('name', 'Jānis')
        ->set('surname', 'Bērziņš')
        ->set('phone', '+37120000000')
        ->set('email', 'test@example.com')
        ->call('submitBooking')
        ->assertSet('step', 2)
        ->assertHasErrors('schedule_id');

    Carbon::setTestNow();
});

test('stripe error is visible on customer info step', function () {
    Carbon::setTestNow(Carbon::today()->setTime(8, 0));

    $serviceType = ServiceType::factory()->create();
    $service = Service::factory()->create([
        'service_type_id' => $serviceType->id,
        'is_active' => true,
    ]);

    $todayDayOfWeek = today()->dayOfWeekIso;
    $schedule = Schedule::factory()->create([
        'service_id' => $service->id,
        'day_of_week' => $todayDayOfWeek,
        'start_time' => '10:00',
        'is_active' => true,
    ]);

    $this->mock(CreateStripeCheckoutSession::class)
        ->shouldReceive('execute')
        ->andThrow(new UnknownApiErrorException('Test error'));

    Livewire::test('booking-modal')
        ->set('service_type_id', $serviceType->id)
        ->set('service_id', $service->id)
        ->set('selectedDate', today()->toDateString())
        ->set('schedule_id', $schedule->id)
        ->set('step', 4)
        ->set('name', 'Jānis')
        ->set('surname', 'Bērziņš')
        ->set('phone', '+37120000000')
        ->set('email', 'test@example.com')
        ->call('submitBooking')
        ->assertSet('step', 3)
        ->assertHasErrors('booking');

    Carbon::setTestNow();
});

test('double submit is prevented by isProcessing guard', function () {
    Carbon::setTestNow(Carbon::today()->setTime(8, 0));

    $serviceType = ServiceType::factory()->create();
    $service = Service::factory()->create([
        'service_type_id' => $serviceType->id,
        'is_active' => true,
    ]);

    $todayDayOfWeek = today()->dayOfWeekIso;
    $schedule = Schedule::factory()->create([
        'service_id' => $service->id,
        'day_of_week' => $todayDayOfWeek,
        'start_time' => '10:00',
        'is_active' => true,
    ]);

    $this->mock(CreateStripeCheckoutSession::class)
        ->shouldReceive('execute')
        ->once()
        ->andReturn(['url' => 'https://checkout.stripe.com/test', 'session_id' => 'cs_test']);

    $component = Livewire::test('booking-modal')
        ->set('service_type_id', $serviceType->id)
        ->set('service_id', $service->id)
        ->set('selectedDate', today()->toDateString())
        ->set('schedule_id', $schedule->id)
        ->set('step', 4)
        ->set('name', 'Jānis')
        ->set('surname', 'Bērziņš')
        ->set('phone', '+37120000000')
        ->set('email', 'test@example.com')
        ->call('submitBooking');

    // Second call should be blocked by isProcessing
    $component->call('submitBooking');

    expect(Booking::count())->toBe(1);

    Carbon::setTestNow();
});

test('step 1 shows cancel button', function () {
    Livewire::test('booking-modal')
        ->assertSee(__('Atcelt'));
});

test('goToStep only allows going backwards', function () {
    Livewire::test('booking-modal')
        ->set('step', 3)
        ->call('goToStep', 1)
        ->assertSet('step', 1);
});

test('goToStep does not allow going forwards', function () {
    Livewire::test('booking-modal')
        ->set('step', 2)
        ->call('goToStep', 4)
        ->assertSet('step', 2);
});

test('duplicate session is rejected in membership builder', function () {
    Carbon::setTestNow(Carbon::today()->setTime(8, 0));

    $serviceType = ServiceType::factory()->create();
    $membershipService = Service::factory()->membership(2)->create();
    $eligibleService = Service::factory()->membershipEligible()->create([
        'service_type_id' => $serviceType->id,
        'is_active' => true,
    ]);

    $todayDayOfWeek = today()->dayOfWeekIso;
    $schedule = Schedule::factory()->create([
        'service_id' => $eligibleService->id,
        'day_of_week' => $todayDayOfWeek,
        'start_time' => '10:00',
        'is_active' => true,
    ]);

    $component = Livewire::test('booking-modal')
        ->set('mode', 'membership')
        ->set('selectedMembershipServiceId', $membershipService->id)
        ->set('step', 2)
        ->set('session_service_type_id', $serviceType->id)
        ->set('session_service_id', $eligibleService->id)
        ->set('session_date', today()->toDateString())
        ->set('session_schedule_id', $schedule->id)
        ->call('addSession')
        ->assertSet('step', 2); // Still on step 2, needs one more session

    expect($component->get('sessions'))->toHaveCount(1);

    // Try to add the same session again
    $component
        ->set('session_service_type_id', $serviceType->id)
        ->set('session_service_id', $eligibleService->id)
        ->set('session_date', today()->toDateString())
        ->set('session_schedule_id', $schedule->id)
        ->call('addSession')
        ->assertHasErrors('session_schedule_id');

    expect($component->get('sessions'))->toHaveCount(1);

    Carbon::setTestNow();
});

test('step indicator shows labels for booking mode', function () {
    Livewire::test('booking-modal')
        ->assertSee(__('Treniņš'))
        ->assertSee(__('Laiks'))
        ->assertSee(__('Dati'))
        ->assertSee(__('Maksājums'));
});

test('membership build sets period end 12 weeks from purchase', function () {
    Carbon::setTestNow(Carbon::today()->setTime(8, 0));

    $this->mock(CreateMembershipCheckoutSession::class)
        ->shouldReceive('execute')
        ->andReturn(['url' => 'https://stripe.test/checkout', 'session_id' => 'cs_test_123']);

    $serviceType = ServiceType::factory()->create();
    $membershipService = Service::factory()->membership(2)->create();
    $eligibleService = Service::factory()->membershipEligible()->create([
        'service_type_id' => $serviceType->id,
        'is_active' => true,
    ]);

    $todayDayOfWeek = today()->dayOfWeekIso;
    $scheduleOne = Schedule::factory()->create([
        'service_id' => $eligibleService->id,
        'day_of_week' => $todayDayOfWeek,
        'start_time' => '10:00',
        'is_active' => true,
    ]);
    $scheduleTwo = Schedule::factory()->create([
        'service_id' => $eligibleService->id,
        'day_of_week' => $todayDayOfWeek,
        'start_time' => '12:00',
        'is_active' => true,
    ]);

    Livewire::test('booking-modal')
        ->set('mode', 'membership')
        ->set('selectedMembershipServiceId', $membershipService->id)
        ->set('step', 2)
        ->set('session_service_type_id', $serviceType->id)
        ->set('session_service_id', $eligibleService->id)
        ->set('session_date', today()->toDateString())
        ->set('session_schedule_id', $scheduleOne->id)
        ->call('addSession')
        ->set('session_service_type_id', $serviceType->id)
        ->set('session_service_id', $eligibleService->id)
        ->set('session_date', today()->toDateString())
        ->set('session_schedule_id', $scheduleTwo->id)
        ->call('addSession')
        ->set('name', 'Test')
        ->set('surname', 'User')
        ->set('phone', '12345678')
        ->set('email', 'test@example.com')
        ->call('submitMembership');

    $membership = Membership::firstOrFail();
    expect($membership->period_start->toDateString())->toBe(today()->toDateString())
        ->and($membership->period_end->toDateString())->toBe(today()->addWeeks(12)->toDateString());

    Carbon::setTestNow();
});

test('selection summary shows service name on step 2', function () {
    $serviceType = ServiceType::factory()->create();
    $service = Service::factory()->create([
        'service_type_id' => $serviceType->id,
        'is_active' => true,
    ]);
    Schedule::factory()->create([
        'service_id' => $service->id,
        'is_active' => true,
    ]);

    Livewire::test('booking-modal')
        ->set('service_type_id', $serviceType->id)
        ->set('service_id', $service->id)
        ->call('nextStep')
        ->assertSet('step', 2)
        ->assertSee($service->name);
});
