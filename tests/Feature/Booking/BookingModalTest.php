<?php

use App\Models\Schedule;
use App\Models\Service;
use App\Models\ServiceType;
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
