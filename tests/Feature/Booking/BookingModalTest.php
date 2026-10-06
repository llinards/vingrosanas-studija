<?php

use App\Actions\CreateMembershipCheckoutSession;
use App\Models\Membership;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\ServiceType;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

test('booking modal can be rendered on welcome page', function () {
    $this->get(route('home'))
        ->assertSuccessful()
        ->assertSeeLivewire('booking-modal');
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

test('validation messages are in latvian', function () {
    Livewire::test('booking-modal')
        ->set('step', 3)
        ->call('nextStep')
        ->assertSee('Vārds ir obligāts.');
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

test('step indicator shows the membership steps', function () {
    Livewire::test('booking-modal')
        ->assertSee(__('Abonements'))
        ->assertSee(__('Nodarbības'))
        ->assertSee(__('Dati'))
        ->assertSee(__('Maksājums'));
});

test('membership period starts on the earliest selected session date', function () {
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
        ->and($membership->period_end->toDateString())->toBe(today()->addMonth()->toDateString());

    Carbon::setTestNow();
});

test('first session can be picked up to three months ahead and anchors the period', function () {
    Carbon::setTestNow(Carbon::today()->setTime(8, 0));

    $this->mock(CreateMembershipCheckoutSession::class)
        ->shouldReceive('execute')
        ->andReturn(['url' => 'https://stripe.test/checkout', 'session_id' => 'cs_test_123']);

    $firstDate = today()->addMonths(3);

    $serviceType = ServiceType::factory()->create();
    $membershipService = Service::factory()->membership(2)->create();
    $eligibleService = Service::factory()->membershipEligible()->create([
        'service_type_id' => $serviceType->id,
        'is_active' => true,
    ]);

    $scheduleOne = Schedule::factory()->create([
        'service_id' => $eligibleService->id,
        'day_of_week' => $firstDate->dayOfWeekIso,
        'start_time' => '10:00',
        'is_active' => true,
    ]);
    $scheduleTwo = Schedule::factory()->create([
        'service_id' => $eligibleService->id,
        'day_of_week' => $firstDate->dayOfWeekIso,
        'start_time' => '12:00',
        'is_active' => true,
    ]);

    Livewire::test('booking-modal')
        ->set('selectedMembershipServiceId', $membershipService->id)
        ->set('step', 2)
        ->set('session_service_type_id', $serviceType->id)
        ->set('session_service_id', $eligibleService->id)
        ->set('session_date', $firstDate->toDateString())
        ->set('session_schedule_id', $scheduleOne->id)
        ->call('addSession')
        ->set('session_service_type_id', $serviceType->id)
        ->set('session_service_id', $eligibleService->id)
        ->set('session_date', $firstDate->toDateString())
        ->set('session_schedule_id', $scheduleTwo->id)
        ->call('addSession')
        ->set('name', 'Test')
        ->set('surname', 'User')
        ->set('phone', '12345678')
        ->set('email', 'test@example.com')
        ->call('submitMembership');

    $membership = Membership::firstOrFail();
    expect($membership->period_start->toDateString())->toBe($firstDate->toDateString())
        ->and($membership->period_end->toDateString())->toBe($firstDate->copy()->addMonth()->toDateString());

    Carbon::setTestNow();
});

test('first session beyond three months is rejected', function () {
    Carbon::setTestNow(Carbon::today()->setTime(8, 0));

    $serviceType = ServiceType::factory()->create();
    $membershipService = Service::factory()->membership(2)->create();
    $eligibleService = Service::factory()->membershipEligible()->create([
        'service_type_id' => $serviceType->id,
        'is_active' => true,
    ]);
    $farDate = today()->addMonths(4);
    $schedule = Schedule::factory()->create([
        'service_id' => $eligibleService->id,
        'day_of_week' => $farDate->dayOfWeekIso,
        'start_time' => '10:00',
        'is_active' => true,
    ]);

    $component = Livewire::test('booking-modal')
        ->set('selectedMembershipServiceId', $membershipService->id)
        ->set('step', 2)
        ->set('session_service_type_id', $serviceType->id)
        ->set('session_service_id', $eligibleService->id)
        ->set('session_date', $farDate->toDateString())
        ->set('session_schedule_id', $schedule->id)
        ->call('addSession')
        ->assertHasErrors('session_date');

    expect($component->get('sessions'))->toHaveCount(0);

    Carbon::setTestNow();
});

test('subsequent sessions outside the one month window are rejected', function () {
    Carbon::setTestNow(Carbon::today()->setTime(8, 0));

    $firstDate = today()->addMonth();

    $serviceType = ServiceType::factory()->create();
    $membershipService = Service::factory()->membership(3)->create();
    $eligibleService = Service::factory()->membershipEligible()->create([
        'service_type_id' => $serviceType->id,
        'is_active' => true,
    ]);
    $schedule = Schedule::factory()->create([
        'service_id' => $eligibleService->id,
        'day_of_week' => $firstDate->dayOfWeekIso,
        'start_time' => '10:00',
        'is_active' => true,
    ]);

    $component = Livewire::test('booking-modal')
        ->set('selectedMembershipServiceId', $membershipService->id)
        ->set('step', 2)
        ->set('session_service_type_id', $serviceType->id)
        ->set('session_service_id', $eligibleService->id)
        ->set('session_date', $firstDate->toDateString())
        ->set('session_schedule_id', $schedule->id)
        ->call('addSession');

    expect($component->get('sessions'))->toHaveCount(1);

    // Before the anchor date.
    $component
        ->set('session_service_type_id', $serviceType->id)
        ->set('session_service_id', $eligibleService->id)
        ->set('session_date', today()->toDateString())
        ->set('session_schedule_id', $schedule->id)
        ->call('addSession')
        ->assertHasErrors('session_date');

    // After the one-month window from the anchor.
    $component
        ->set('session_date', $firstDate->copy()->addMonths(2)->toDateString())
        ->set('session_schedule_id', $schedule->id)
        ->call('addSession')
        ->assertHasErrors('session_date');

    expect($component->get('sessions'))->toHaveCount(1);

    Carbon::setTestNow();
});

test('submission is rejected when sessions fall outside the allowed window', function () {
    Carbon::setTestNow(Carbon::today()->setTime(8, 0));

    $farDate = today()->addMonths(6);

    $serviceType = ServiceType::factory()->create();
    $membershipService = Service::factory()->membership(2)->create();
    $eligibleService = Service::factory()->membershipEligible()->create([
        'service_type_id' => $serviceType->id,
        'is_active' => true,
    ]);
    $schedule = Schedule::factory()->create([
        'service_id' => $eligibleService->id,
        'day_of_week' => $farDate->dayOfWeekIso,
        'start_time' => '10:00',
        'is_active' => true,
    ]);

    // Bypass the calendar guard by injecting crafted sessions directly.
    $craftedSessions = [
        [
            'service_id' => $eligibleService->id,
            'service_name' => $eligibleService->name,
            'coach_name' => '',
            'schedule_id' => $schedule->id,
            'date' => $farDate->toDateString(),
            'time' => '10:00',
        ],
        [
            'service_id' => $eligibleService->id,
            'service_name' => $eligibleService->name,
            'coach_name' => '',
            'schedule_id' => $schedule->id,
            'date' => $farDate->copy()->addDay()->toDateString(),
            'time' => '10:00',
        ],
    ];

    Livewire::test('booking-modal')
        ->set('selectedMembershipServiceId', $membershipService->id)
        ->set('step', 2)
        ->set('sessions', $craftedSessions)
        ->set('name', 'Test')
        ->set('surname', 'User')
        ->set('phone', '12345678')
        ->set('email', 'test@example.com')
        ->call('submitMembership')
        ->assertHasErrors('sessions')
        ->assertSet('step', 2);

    expect(Membership::count())->toBe(0);

    Carbon::setTestNow();
});
