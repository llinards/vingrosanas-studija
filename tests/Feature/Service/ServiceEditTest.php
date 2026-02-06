<?php

use App\Models\Coach;
use App\Models\Service;
use App\Models\ServiceType;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('service edit page can be rendered', function () {
    $service = Service::factory()->create();

    $this->get(route('service.edit', $service))
        ->assertSuccessful()
        ->assertSeeLivewire('service.service-edit');
});

test('service edit page requires authentication', function () {
    $service = Service::factory()->create();

    auth()->logout();

    $this->get(route('service.edit', $service))
        ->assertRedirect(route('login'));
});

test('service edit form is populated with existing data', function () {
    $serviceType = ServiceType::factory()->create();
    $coach = Coach::factory()->create();

    $service = Service::factory()->create([
        'name' => 'Jogas nodarbība',
        'service_type_id' => $serviceType->id,
        'coach_id' => $coach->id,
        'price' => 2500,
    ]);

    Livewire::test('service.service-edit', ['service' => $service])
        ->assertSet('name', 'Jogas nodarbība')
        ->assertSet('service_type_id', $serviceType->id)
        ->assertSet('coach_id', $coach->id)
        ->assertSet('price', '25')
        ->assertSet('is_active', true);
});

test('can update a service', function () {
    $service = Service::factory()->create();
    $newServiceType = ServiceType::factory()->create();
    $newCoach = Coach::factory()->create();

    Livewire::test('service.service-edit', ['service' => $service])
        ->set('name', 'Atjaunināts pakalpojums')
        ->set('service_type_id', $newServiceType->id)
        ->set('coach_id', $newCoach->id)
        ->set('price', '30.00')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('service-list'));

    $this->assertDatabaseHas('services', [
        'id' => $service->id,
        'name' => 'Atjaunināts pakalpojums',
        'service_type_id' => $newServiceType->id,
        'coach_id' => $newCoach->id,
        'price' => 3000,
    ]);
});

test('name is required', function () {
    $service = Service::factory()->create();

    Livewire::test('service.service-edit', ['service' => $service])
        ->set('name', '')
        ->call('save')
        ->assertHasErrors(['name' => 'required']);
});

test('service type is required', function () {
    $service = Service::factory()->create();

    Livewire::test('service.service-edit', ['service' => $service])
        ->set('service_type_id', null)
        ->call('save')
        ->assertHasErrors(['service_type_id' => 'required']);
});

test('coach is required', function () {
    $service = Service::factory()->create();

    Livewire::test('service.service-edit', ['service' => $service])
        ->set('coach_id', null)
        ->call('save')
        ->assertHasErrors(['coach_id' => 'required']);
});

test('price is required', function () {
    $service = Service::factory()->create();

    Livewire::test('service.service-edit', ['service' => $service])
        ->set('price', '')
        ->call('save')
        ->assertHasErrors(['price' => 'required']);
});

test('price must be numeric', function () {
    $service = Service::factory()->create();

    Livewire::test('service.service-edit', ['service' => $service])
        ->set('price', 'abc')
        ->call('save')
        ->assertHasErrors(['price' => 'numeric']);
});

test('price cannot be negative', function () {
    $service = Service::factory()->create();

    Livewire::test('service.service-edit', ['service' => $service])
        ->set('price', '-5')
        ->call('save')
        ->assertHasErrors(['price' => 'min']);
});

test('can toggle is_active on a service', function () {
    $service = Service::factory()->create(['is_active' => true]);
    $serviceType = $service->serviceType;
    $coach = $service->coach;

    Livewire::test('service.service-edit', ['service' => $service])
        ->set('is_active', false)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('service-list'));

    $this->assertDatabaseHas('services', [
        'id' => $service->id,
        'is_active' => false,
    ]);
});

test('validation messages are in latvian', function () {
    $service = Service::factory()->create();

    Livewire::test('service.service-edit', ['service' => $service])
        ->set('name', '')
        ->call('save')
        ->assertSee('Nosaukums ir obligāts.');
});
