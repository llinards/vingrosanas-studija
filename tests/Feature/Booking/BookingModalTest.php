<?php

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
